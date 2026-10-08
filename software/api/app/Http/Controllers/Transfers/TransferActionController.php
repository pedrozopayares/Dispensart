<?php

namespace App\Http\Controllers\Transfers;

use App\Actions\Transfers\ApproveTransfer;
use App\Actions\Transfers\DispatchTransfer;
use App\Actions\Transfers\ReceiveTransfer;
use App\Actions\Transfers\RequestTransfer;
use App\Actions\Transfers\VoidTransfer;
use App\Http\Requests\Transfers\ReceiveTransferRequest;
use App\Http\Requests\Transfers\VoidTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Transiciones del traslado (RN-07). Permiso en la ruta o en el FormRequest; estado, segregación y stock en la
 * acción.
 */
final class TransferActionController
{
    /**
     * BORRADOR → SOLICITADO, solo por su creador.
     */
    public function request(Request $request, Transfer $transfer, RequestTransfer $action): TransferResource
    {
        return new TransferResource($action->handle(self::user($request), $transfer));
    }

    /**
     * SOLICITADO → APROBADO por un usuario con transfers.approve distinto del solicitante (RN-08).
     */
    public function approve(Request $request, Transfer $transfer, ApproveTransfer $action): TransferResource
    {
        return new TransferResource($action->handle(self::user($request), $transfer));
    }

    /**
     * APROBADO → EN_TRANSITO: un salida_traslado por línea en origen, todo o nada.
     */
    public function dispatch(Request $request, Transfer $transfer, DispatchTransfer $action): TransferResource
    {
        return new TransferResource($action->handle(self::user($request), $transfer));
    }

    /**
     * EN_TRANSITO → RECIBIDO | RECIBIDO_PARCIAL: entrada_traslado en destino y discrepancias por faltante.
     */
    public function receive(ReceiveTransferRequest $request, Transfer $transfer, ReceiveTransfer $action): TransferResource
    {
        return new TransferResource($action->handle(self::user($request), $transfer, $request->received()));
    }

    /**
     * BORRADOR, SOLICITADO o APROBADO → ANULADO con motivo, sin stock.
     */
    public function void(VoidTransferRequest $request, Transfer $transfer, VoidTransfer $action): TransferResource
    {
        return new TransferResource($action->handle(self::user($request), $transfer, $request->reason()));
    }

    private static function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
