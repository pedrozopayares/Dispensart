<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Único lugar que calcula "hoy" para reglas de negocio (design D6), en la zona de FARTMAR.
 */
final class BusinessCalendar
{
    public static function today(): CarbonImmutable
    {
        /** @var string $timezone */
        $timezone = config('dispensart.business_timezone');

        return now()->toImmutable()->setTimezone($timezone)->startOfDay();
    }
}
