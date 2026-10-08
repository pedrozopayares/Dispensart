<?php

namespace App\Logging;

use App\Http\Middleware\AssignCorrelationId;
use DateTimeInterface;
use DateTimeZone;
use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Una línea JSON por entrada con claves planas: timestamp (ISO-8601 UTC), level, message,
 * correlation_id (null fuera de una petición) y context (design D6).
 */
final class JsonLineFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct(self::BATCH_MODE_NEWLINES, appendNewline: true);
    }

    public function format(LogRecord $record): string
    {
        $correlationId = $record->extra[AssignCorrelationId::CONTEXT_KEY] ?? null;

        $line = [
            'timestamp' => $record->datetime
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DateTimeInterface::RFC3339_EXTENDED),
            'level' => $record->level->toPsrLogLevel(),
            'message' => $record->message,
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'context' => $record->context === [] ? new \stdClass : $this->normalize($record->context),
        ];

        return $this->toJson($line, true).($this->appendNewline ? "\n" : '');
    }
}
