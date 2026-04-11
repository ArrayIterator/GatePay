<?php
declare(strict_types=1);

namespace GatePay\Core;

use GatePay\Core\Exceptions\TransactionException;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use GuzzleHttp\Exception\RequestException;
use Throwable;

class TransactionError implements TransactionErrorInterface
{
    /**
     * TransactionError constructor.
     *
     * @param TransactionException $exception The exception that caused the transaction to fail.
     */
    public function __construct(public readonly TransactionException $exception)
    {
    }

    /**
     * Factory method to create a TransactionError instance from a TransactionException.
     *
     * @param TransactionException $exception The exception that caused the transaction to fail.
     * @return self A new instance of TransactionError containing the provided exception.
     */
    public static function create(TransactionException $exception): self
    {
        return new self($exception);
    }

    /**
     * Factory method to create a TransactionError instance from a TransactionInterface and a Throwable.
     *
     * @param TransactionInterface $transaction The transaction that failed.
     * @param Throwable $exception The exception that caused the transaction to fail.
     * @return self A new instance of TransactionError containing the provided transaction and exception.
     */
    public static function createFromTransaction(
        TransactionInterface $transaction,
        Throwable $exception
    ): self {
        return self::create(new TransactionException(
            transaction: $transaction,
            message: $exception->getMessage(),
            code: $exception->getCode(),
            previous: $exception
        ));
    }

    /**
     * @inheritdoc
     */
    public function getTransaction(): TransactionInterface
    {
        return $this->exception->getTransaction();
    }

    /**
     * @inheritdoc
     */
    public function getException(): TransactionException
    {
        return $this->exception;
    }

    /**
     * @inheritdoc
     */
    public function isHttpError(): bool
    {
        if ($this->exception instanceof TransactionException) {
            $previous = $this->exception->getPrevious();
            if ($previous instanceof RequestException) {
                return true;
            }
        }
        return false;
    }
}
