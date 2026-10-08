<?php

namespace App\Http\Requests\Transfers;

use App\Enums\DiscrepancyResolution;
use App\Models\Transfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resolución de una discrepancia (transfers "Resolución de discrepancias"): transfers.approve, resolución
 * conocida y motivo obligatorio (≤ 500; de solo espacios llega como null y falla `required`).
 */
final class ResolveDiscrepancyRequest extends FormRequest
{
    public const MAX_REASON = 500;

    public function authorize(): bool
    {
        /** @var Transfer $transfer */
        $transfer = $this->route('transfer');

        return (bool) $this->user()?->can('resolveDiscrepancy', $transfer);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', Rule::in(DiscrepancyResolution::values())],
            'reason' => ['required', 'string', 'max:'.self::MAX_REASON],
        ];
    }

    /**
     * @return array{resolution: DiscrepancyResolution, reason: string}
     */
    public function resolution(): array
    {
        return [
            'resolution' => DiscrepancyResolution::from($this->string('resolution')->toString()),
            'reason' => $this->string('reason')->toString(),
        ];
    }
}
