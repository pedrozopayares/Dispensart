<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as Monolog;

/**
 * Instala JsonLineFormatter en los manejadores del canal (config/logging.php, canal stderr) y la redacción de
 * excepciones en todo registro del canal (RedactExceptionProcessor, design D9 de S3).
 */
final class JsonLineTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof Monolog) {
            return;
        }

        $monolog->pushProcessor(new RedactExceptionProcessor);

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonLineFormatter);
            }
        }
    }
}
