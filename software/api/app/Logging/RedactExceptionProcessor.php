<?php

namespace App\Logging;

use Illuminate\Database\QueryException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Redacción de excepciones en el log (RN-10, design D9 de S3). Un mensaje de excepción puede llevar datos de
 * paciente (valores enlazados de SQL, nombres, documentos, ids de modelo): se sustituye el mensaje por la
 * clase y el contexto por {class, code, file, line, constraint?}. `code` es el SQLSTATE en QueryException y
 * `constraint` solo el nombre de la restricción, nunca el DETAIL con valores. Sin traza ni `previous`.
 * Un 500 se depura con clase + archivo:línea + SQLSTATE + restricción + correlation_id.
 */
final class RedactExceptionProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;

        if (! $exception instanceof Throwable) {
            return $record;
        }

        $context = [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        if ($exception instanceof QueryException) {
            $context['code'] = self::sqlState($exception);
            if (preg_match('/constraint "([a-z0-9_]+)"/', $exception->getMessage(), $match) === 1) {
                $context['constraint'] = $match[1];
            }
        }

        return $record->with(message: $exception::class, context: $context);
    }

    /**
     * SQLSTATE de cinco caracteres: el de errorInfo, o el del prefijo `SQLSTATE[…]` (fallos de conexión, cuyo
     * código PDO es numérico). Solo el código, nunca el resto del mensaje.
     */
    private static function sqlState(QueryException $exception): string|int
    {
        $state = $exception->errorInfo[0] ?? null;
        if (is_string($state) && preg_match('/^[0-9A-Z]{5}$/', $state) === 1) {
            return $state;
        }

        return preg_match('/SQLSTATE\[([0-9A-Z]{5})\]/', $exception->getMessage(), $match) === 1
            ? $match[1]
            : $exception->getCode();
    }
}
