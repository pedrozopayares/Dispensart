<?php

namespace App\Services\Assistant;

/**
 * Estado de cada llamada a herramienta en `tool_calls` (inventory-assistant).
 */
enum ToolCallStatus: string
{
    case Ok = 'ok';
    case Denied = 'denied';
    case Rejected = 'rejected';
    case InvalidArguments = 'invalid_arguments';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
