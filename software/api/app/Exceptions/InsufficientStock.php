<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La operación dejaría una existencia negativa o la existencia no existe (RN-03). HTTP 409
 * insufficient_stock. La dispensación (S3) adjunta `shortages` por ítem; el libro, que nunca debería
 * llegar aquí tras la asignación FEFO, la lanza sin ellos.
 */
final class InsufficientStock extends RuntimeException
{
    /**
     * @param  list<array{prescription_item_id: int, product_id: int, requested: int, available: int}>  $shortages
     */
    public function __construct(public readonly array $shortages = [])
    {
        parent::__construct('Insufficient stock.');
    }
}
