<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Interfaces;

use ArrayIterator\GatePay\Enum\GatewayAction;

/**
 * This interface defines the contract for a transaction that can be processed by a payment gateway.
 * It requires implementing classes to provide a method to retrieve the specific action associated with the transaction.
 */
interface TransactionInterface extends TransactionStackInterface
{
    /**
     * Get the unique identifier for the transaction.
     *
     * @return non-empty-string The transaction ID.
     */
    public function getTransactionId(): string;

    /**
     * Get the specific payment action associated with this transaction.
     *
     * @return GatewayAction The payment action (e.g., charge, refund) that this transaction represents.
     */
    public function getAction() : GatewayAction;

    /**
     * Get any additional parameters or data associated with the transaction.
     *
     * @return array<array-key, mixed>
     * An associative array of parameters relevant to the transaction,
     * such as amount, currency, customer details, etc.
     * This for satisfy
     * @see GatewayActionInterface::createRequest()
     * @see GatewayActionInterface::isProcessable()
     */
    public function getParameters() : array;

    /**
     * Get the response associated with the transaction, if available.
     *
     * @return ?TransactionResponseInterface
     * Returns the transaction response if available, or null if not applicable.
     */
    public function getResponse() : ?TransactionResponseInterface;

    /**
     * Get the error information associated with the transaction, if available.
     *
     * @return ?TransactionErrorInterface
     * Returns the transaction error information if available, or null if no error occurred.
     */
    public function getError() : ?TransactionErrorInterface;
}
