<?php
declare(strict_types=1);

namespace GatePay\Core\Interfaces;

use GatePay\Core\Enum\TransactionStatus;
use Psr\Http\Message\ResponseInterface;

/**
 * This interface defines the contract for the response of a transaction processed by a payment gateway.
 * It can be implemented by classes that represent the result of a transaction, containing details about
 * whether the transaction was successful, any relevant data, and error information if applicable.
 */
interface TransactionResponseInterface
{
    /**
     * Get the transaction associated with this response.
     *
     * @return TransactionInterface Returns the transaction that this response corresponds to.
     */
    public function getTransaction(): TransactionInterface;

    /**
     * Get the status of the transaction.
     *
     * @return TransactionStatus Returns the status of the transaction,
     * indicating its current state (e.g., pending, completed, failed).
     */
    public function getStatus() : TransactionStatus;

    /**
     * @return ResponseInterface Returns the raw response from the payment gateway,
     * which may contain additional details about the transaction result,
     * such as response codes, messages, or data returned by the gateway.
     */
    public function getResponse() : ResponseInterface;
}
