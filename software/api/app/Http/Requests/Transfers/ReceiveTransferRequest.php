<?php

namespace App\Http\Requests\Transfers;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Recepción (transfers "Recepción del traslado", design D6): transfers.receive. Exactamente las líneas del
 * traslado, sin repetir, con 0 ≤ recibido ≤ cantidad de su línea. La sobre-recepción solo se rechaza aquí como
 * 422; más adentro es un defecto. Las líneas son inmutables tras crear: leerlas sin bloqueo es seguro.
 */
final class ReceiveTransferRequest extends FormRequest
{
    /** @var array<int, int>|null id de línea → cantidad */
    private ?array $quantities = null;

    public function authorize(): bool
    {
        $transfer = $this->transferModel();

        return $transfer !== null && (bool) $this->user()?->can('receive', $transfer);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $quantities = $this->lineQuantities();

        return [
            'lines' => ['required', 'array', 'list', 'size:'.count($quantities)],
            'lines.*' => ['required', 'array'],
            'lines.*.line_id' => ['bail', 'required', 'integer', 'distinct', Rule::in(array_keys($quantities))],
            'lines.*.received_quantity' => ['bail', 'required', 'integer', 'min:0'],
        ];
    }

    /**
     * Recibido ≤ cantidad de su línea, con el error en `lines.N.received_quantity`.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $quantities = $this->lineQuantities();
            $lines = $this->input('lines');
            if (! is_array($lines)) {
                return;
            }

            foreach ($lines as $index => $line) {
                $field = "lines.{$index}.received_quantity";
                if (! is_array($line) || $validator->errors()->hasAny([$field, "lines.{$index}.line_id"])) {
                    continue;
                }

                $max = $quantities[(int) $line['line_id']];
                if ((int) $line['received_quantity'] > $max) {
                    $validator->errors()->add($field, __('validation.max.numeric', [
                        'attribute' => $validator->getDisplayableAttribute($field),
                        'max' => $max,
                    ]));
                }
            }
        }];
    }

    /**
     * @return array<int, int> id de línea → cantidad recibida
     */
    public function received(): array
    {
        /** @var array{lines: list<array{line_id: int|string, received_quantity: int|string}>} $data */
        $data = $this->validated();

        $received = [];
        foreach ($data['lines'] as $line) {
            $received[(int) $line['line_id']] = (int) $line['received_quantity'];
        }

        return $received;
    }

    /**
     * @return array<int, int>
     */
    private function lineQuantities(): array
    {
        // Sin traslado enlazado (p. ej. al inferir el contrato OpenAPI) no hay líneas.
        return $this->quantities ??= $this->transferModel()?->lines()->pluck('quantity', 'id')
            ->mapWithKeys(fn ($quantity, $id): array => [(int) $id => (int) $quantity])
            ->all() ?? [];
    }

    private function transferModel(): ?Transfer
    {
        $transfer = $this->route('transfer');

        return $transfer instanceof Transfer ? $transfer : null;
    }
}
