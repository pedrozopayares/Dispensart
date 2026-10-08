<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El proveedor del asistente no respondió, respondió con error, superó el plazo o no está configurado
 * (inventory-assistant «Proveedor configurable por entorno»). Responde 503 `assistant_unavailable` sin URL,
 * traza ni texto del proveedor.
 */
final class AssistantUnavailable extends RuntimeException {}
