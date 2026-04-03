<?php
declare(strict_types=1);

namespace GatePay\Core\Exceptions;

use GatePay\Core\Interfaces\TransactionInterface;
use RuntimeException;
use Throwable;

class TransactionException extends RuntimeException
{
    public function __construct(
        public readonly TransactionInterface $transaction,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return TransactionInterface
     */
    public function getTransaction(): TransactionInterface
    {
        return $this->transaction;
    }
}
