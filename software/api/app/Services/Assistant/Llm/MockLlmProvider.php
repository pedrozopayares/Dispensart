<?php

namespace App\Services\Assistant\Llm;

use App\Enums\TransferStatus;
use App\Services\Assistant\Text;

/**
 * Proveedor simulado por defecto (AI_PROVIDER=mock; design D10): decide herramienta y argumentos por reglas sobre
 * la pregunta, sin red, reloj ni aleatoriedad. Como un modelo real, solo elige: la respuesta la compone el
 * servidor. Reglas de la primera ronda, en orden:
 * 1. escritura, SQL o intento de redefinir las reglas → texto, sin herramienta;
 * 2. intención: traslado → get_transfer_status; mínimo/bajo → get_low_stock_alerts; vencimiento →
 *    find_expiring_lots; stock/existencias/cuánto/hay → get_stock; ninguna → texto;
 * 3. producto y bodega del catálogo contenidos en la pregunta, sin tildes ni mayúsculas (el más largo gana);
 *    plazo en días; número o estado de traslado.
 * Con resultados de herramientas ya en la conversación responde "listo".
 */
final class MockLlmProvider implements LlmProvider
{
    private const REFUSAL = '/\b(aprueba|aprobar|despacha|despachar|recibe|recibir|anula|anular|ajusta|ajustar|crea|crear|'
        .'borra|borrar|elimina|eliminar|modifica|modificar|actualiza|actualizar|cambia|cambiar|registra|registrar|'
        .'select|insert|update|delete|drop|sql|tabla|olvida|ignora|ahora eres)\b/';

    /** @var array<string, string> intención → herramienta, en orden de prioridad */
    private const INTENTS = [
        '/traslado/' => 'get_transfer_status',
        '/minimo|bajo/' => 'get_low_stock_alerts',
        '/venc|caduc/' => 'find_expiring_lots',
        '/stock|existencia|cuant|\bhay\b/' => 'get_stock',
    ];

    /** @var array<string, TransferStatus> */
    private const STATUS_WORDS = [
        'transito' => TransferStatus::InTransit,
        'parcial' => TransferStatus::PartiallyReceived,
        'recibid' => TransferStatus::Received,
        'solicitad' => TransferStatus::Requested,
        'aprobad' => TransferStatus::Approved,
        'borrador' => TransferStatus::Draft,
        'anulad' => TransferStatus::Voided,
    ];

    /** Mención de un producto fuera del catálogo: "lotes de X", "stock de X". */
    private const PRODUCT_MENTION = '/\b(?:lotes|stock|existencias?|unidades) de ([a-z][a-z0-9]{3,})\b/';

    public function __construct(private readonly CatalogVocabulary $vocabulary) {}

    public function name(): string
    {
        return 'mock';
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        if ($request->hasToolResults()) {
            return ChatResponse::text('listo');
        }

        $question = Text::normalize($request->firstUserMessage());
        if (preg_match(self::REFUSAL, $question) === 1) {
            return ChatResponse::text('No puedo hacer eso.');
        }

        $tool = $this->intent($question);
        if ($tool === null) {
            return ChatResponse::text('Fuera de alcance.');
        }

        $id = 'c'.($request->toolCallsSoFar() + 1);

        return ChatResponse::toolCalls([new ToolCall($id, $tool, $this->arguments($tool, $question))]);
    }

    private function intent(string $question): ?string
    {
        foreach (self::INTENTS as $pattern => $tool) {
            if (preg_match($pattern, $question) === 1) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * @return array<string, int|string>
     */
    private function arguments(string $tool, string $question): array
    {
        $arguments = [];

        if ($tool === 'get_transfer_status') {
            if (preg_match('/#?\s*(\d{1,9})\b/', $question, $match) === 1) {
                return ['transfer_id' => (int) $match[1]];
            }
            foreach (self::STATUS_WORDS as $word => $status) {
                if (str_contains($question, $word)) {
                    $arguments['status'] = $status->value;
                    break;
                }
            }
        }

        if ($tool === 'find_expiring_lots' && preg_match('/(\d{1,9})\s*dias?\b/', $question, $match) === 1) {
            $arguments['days'] = (int) $match[1];
        }

        if (in_array($tool, ['find_expiring_lots', 'get_stock'], true)) {
            $product = $this->product($question);
            if ($product !== null) {
                $arguments['product'] = $product;
            }
        }

        $warehouse = $this->warehouse($question);
        if ($warehouse !== null) {
            $arguments['warehouse'] = $warehouse;
        }

        return $arguments;
    }

    /**
     * Bodega cuyas palabras significativas están todas en la pregunta ("farmacia de urgencias" → Farmacia
     * Urgencias); la de nombre más largo gana.
     */
    private function warehouse(string $question): ?string
    {
        $words = Text::words($question);

        return $this->longest(array_filter(
            $this->vocabulary->warehouses(),
            fn (string $name): bool => Text::words($name) !== [] && array_diff(Text::words($name), $words) === [],
        ));
    }

    /**
     * Producto del catálogo por su nombre completo o por su primera palabra (el principio activo); si no hay,
     * la palabra mencionada como producto ("lotes de X"), que no resolverá en el catálogo.
     */
    private function product(string $question): ?string
    {
        $words = Text::words($question);
        $found = $this->longest(array_filter(
            $this->vocabulary->products(),
            fn (string $name): bool => str_contains($question, Text::normalize($name))
                || in_array(Text::words($name)[0] ?? '', $words, true),
        ));
        if ($found !== null) {
            return $found;
        }

        if (preg_match(self::PRODUCT_MENTION, $question, $match) === 1) {
            $warehouseWords = array_merge(...array_map(fn (string $name): array => Text::words($name), [...$this->vocabulary->warehouses(), '']));

            return in_array($match[1], $warehouseWords, true) ? null : $match[1];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $names
     */
    private function longest(array $names): ?string
    {
        $best = null;
        foreach ($names as $name) {
            if ($best === null || mb_strlen($name) > mb_strlen($best)) {
                $best = $name;
            }
        }

        return $best;
    }
}
