<?php

namespace App\Actions\Transfers;

use App\Enums\TransferStatus;
use App\Exceptions\LotExpired;
use App\Models\Lot;
use App\Models\Transfer;
use App\Models\TransferLine;
use App\Models\User;
use App\Queries\TransferQuery;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\DB;

/**
 * Creación de un traslado en BORRADOR (transfers "Creación de traslados"; design D9 paso 5). Producto derivado
 * del lote, creador = usuario autenticado; estado, actores y fechas los fija el servidor. Un lote vencido se
 * rechaza (RN-01). Crear no verifica ni reserva existencias: eso ocurre al despachar.
 */
final class CreateTransfer
{
    public function __construct(private readonly TransferQuery $query) {}

    /**
     * @param  array{origin_warehouse_id: int, destination_warehouse_id: int, notes: string|null, lines: list<array{lot_id: int, quantity: int}>}  $data
     *
     * @throws LotExpired
     */
    public function handle(User $creator, array $data): Transfer
    {
        /** @var array<int, Lot> $lots */
        $lots = Lot::query()->findMany(array_column($data['lines'], 'lot_id'))->keyBy('id')->all();
        $today = BusinessCalendar::today();
        foreach ($lots as $lot) {
            if ($lot->isExpiredOn($today)) {
                throw new LotExpired;
            }
        }

        $transferId = DB::transaction(function () use ($creator, $data, $lots): int {
            $transfer = (new Transfer)->forceFill([
                'origin_warehouse_id' => $data['origin_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'status' => TransferStatus::Draft,
                'notes' => $data['notes'],
                'created_by' => $creator->id,
            ]);
            $transfer->save();

            foreach ($data['lines'] as $line) {
                (new TransferLine)->forceFill([
                    'transfer_id' => $transfer->id,
                    'lot_id' => $line['lot_id'],
                    'product_id' => $lots[$line['lot_id']]->product_id,
                    'quantity' => $line['quantity'],
                ])->save();
            }

            return $transfer->id;
        });

        return $this->query->detail($transferId);
    }
}
