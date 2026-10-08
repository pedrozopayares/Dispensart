<?php

namespace App\Services\Assistant;

/**
 * Compone `answer` en español desde los resultados `ok` con datos, en orden de llamada, con las plantillas de
 * lang/es/assistant.php (design D8). El texto del modelo nunca se usa, y `notes` de un traslado nunca se
 * renderiza: así ni una cifra inventada ni una observación maliciosa llegan al usuario. Función pura.
 */
final class AnswerComposer
{
    /**
     * @param  list<ToolCallRecord>  $calls
     */
    public function compose(array $calls): string
    {
        $sections = [];
        foreach ($calls as $call) {
            if (! $call->hasData() || $call->data === null) {
                continue;
            }
            $sections[] = match ($call->tool) {
                'find_expiring_lots' => $this->expiring($call->data),
                'get_stock' => $this->stock($call->data['items']),
                'get_low_stock_alerts' => $this->lowStock($call->data['items']),
                'get_transfer_status' => $this->transfers($call->data),
                default => '',
            };
        }

        return implode("\n\n", array_filter($sections, fn (string $section): bool => $section !== ''));
    }

    /**
     * @param  array{items: list<array<string, mixed>>, meta: array<string, mixed>}  $data
     */
    private function expiring(array $data): string
    {
        $lines = [$this->t('expiring.title', ['days' => $data['meta']['days'] ?? ''])];
        foreach ($data['items'] as $item) {
            $lines[] = $this->t('expiring.line', [
                'product' => $item['product'], 'lot' => $item['lot_code'], 'warehouse' => $item['warehouse'],
                'quantity' => $item['quantity'], 'date' => $item['expires_on'],
            ]).($item['is_expired'] === true ? $this->t('expiring.expired') : '');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function stock(array $items): string
    {
        $lines = [$this->t('stock.title')];
        foreach ($items as $item) {
            $lines[] = $this->t('stock.line', [
                'product' => $item['product'], 'warehouse' => $item['warehouse'], 'available' => $item['available_total'],
            ]);
            foreach ($this->list($item['lots'] ?? []) as $lot) {
                $lines[] = $this->t('stock.lot', [
                    'lot' => $lot['lot_code'], 'quantity' => $lot['quantity'], 'date' => $lot['expires_on'],
                ]).($lot['is_expired'] === true ? $this->t('stock.expired') : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function lowStock(array $items): string
    {
        $lines = [$this->t('low_stock.title')];
        foreach ($items as $item) {
            $lines[] = $this->t('low_stock.line', [
                'product' => $item['product'], 'warehouse' => $item['warehouse'],
                'available' => $item['available'], 'minimum' => $item['minimum'],
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{items: list<array<string, mixed>>, meta: array<string, mixed>}  $data
     */
    private function transfers(array $data): string
    {
        if (($data['meta']['mode'] ?? null) === 'counts') {
            $lines = [$this->t('transfer.counts_title')];
            foreach ($data['items'] as $item) {
                $lines[] = $this->t('transfer.count', ['status' => $this->status($item['status']), 'count' => $item['count']]);
            }

            return implode("\n", $lines);
        }

        $lines = [];
        foreach ($data['items'] as $transfer) {
            // `notes` no se lee aquí a propósito (design D8).
            $lines[] = $this->t('transfer.detail', [
                'id' => $transfer['id'], 'status' => $this->status($transfer['status']),
                'origin' => $transfer['origin_warehouse'], 'destination' => $transfer['destination_warehouse'],
            ]);
            foreach ($this->list($transfer['lines'] ?? []) as $line) {
                $lines[] = $this->t('transfer.line', [
                    'product' => $line['product'], 'lot' => $line['lot_code'], 'sent' => $line['quantity'],
                    'received' => $line['received_quantity'] ?? $this->t('transfer.not_received'),
                ]);
            }
            $pending = $this->list($transfer['pending_discrepancies'] ?? []);
            if ($pending !== []) {
                $lines[] = $this->t('transfer.pending_title');
                foreach ($pending as $discrepancy) {
                    $lines[] = $this->t('transfer.pending', [
                        'product' => $discrepancy['product'], 'lot' => $discrepancy['lot_code'], 'shortage' => $discrepancy['shortage'],
                    ]);
                }
            }
        }

        return implode("\n", $lines);
    }

    private function status(mixed $status): string
    {
        return $this->t('status.'.(is_string($status) ? $status : ''));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        /** @var list<array<string, mixed>> */
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function t(string $key, array $replace = []): string
    {
        $strings = array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $replace);

        return (string) __('assistant.'.$key, $strings);
    }
}
