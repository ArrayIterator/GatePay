<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Exceptions;

use RuntimeException;
use Throwable;

class DataFrozenException extends RuntimeException
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $message = $message ?: 'The data is frozen and cannot be modified.';
        parent::__construct($message, $code, $previous);
    }
}
