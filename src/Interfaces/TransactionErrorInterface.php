<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Interfaces;

use ArrayIterator\GatePay\Exceptions\TransactionException;

/**
 * This interface defines the contract for the result of a transaction processed by a payment gateway.
 * It requires implementing classes to provide methods to determine if the transaction was successful,
 * retrieve any error messages, and access additional data related to the transaction result.
 *
 * @template T of TransactionException
 */
interface TransactionErrorInterface
{
    /**
     * Determine if the transaction was successful.
     *
     * @return TransactionInterface
     */
    public function getTransaction(): TransactionInterface;

    /**
     * @return TransactionException
     * Returns the exception that caused the transaction to fail,
     * or null if the transaction was successful.
     */
    public function getException(): TransactionException;

    /**
     * Indicate whether the error was due to an HTTP error (e.g., network issues, server errors).
     * This can be used to differentiate between errors caused by
     * the payment gateway and those caused by other factors.
     * @return bool true if the error was an HTTP error, false otherwise.
     */
    public function isHttpError(): bool;
}
