<?php

namespace App\Http\Requests\Transfers;

use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anulación con motivo (transfers "Anulación del traslado"): creador o transfers.approve. `reason` de solo
 * espacios llega como null (TrimStrings + ConvertEmptyStringsToNull) y falla `required`.
 */
final class VoidTransferRequest extends FormRequest
{
    public const MAX_REASON = 500;

    public function authorize(): bool
    {
        /** @var Transfer $transfer */
        $transfer = $this->route('transfer');

        return (bool) $this->user()?->can('void', $transfer);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:'.self::MAX_REASON],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->toString();
    }
}
