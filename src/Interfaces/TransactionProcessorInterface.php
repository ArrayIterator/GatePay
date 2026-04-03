<?php
declare(strict_types=1);

namespace GatePay\Core\Interfaces;

use GatePay\Core\Enum\TransactionStatus;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * This interface defines the contract for processing a transaction through a payment gateway.
 * It provides methods to retrieve the transaction details, the associated payment gateway,
 * the original request, and the results of processing the transaction, including any responses or errors.
 * Implementing classes will handle the logic for executing the transaction and updating its status accordingly.
 */
interface TransactionProcessorInterface
{
    /**
     * Get the transaction associated with this result.
     *
     * @return TransactionInterface
     */
    public function getTransaction() : TransactionInterface;

    /**
     * Get the payment gateway that processed the transaction.
     *
     * @return GatewayInterface
     */
    public function getGateway() : GatewayInterface;

    /**
     * Get the original request that initiated the transaction.
     *
     * @return RequestInterface
     */
    public function getRequest() : RequestInterface;

    /**
     * Get the status of the transaction.
     *
     * @return TransactionStatus
     */
    public function getTransactionStatus() : TransactionStatus;

    /**
     * Get the response from the payment gateway, if available.
     *
     * @return TransactionResponseInterface|null
     * Returns the transaction response if available, or null if not.
     */
    public function getTransactionResponse() : ?TransactionResponseInterface;

    /**
     * Get the error information if the transaction failed.
     *
     * @return TransactionErrorInterface|null
     * Returns the transaction error if the transaction failed,
     * or null if there was no error or if the transaction was successful.
     */
    public function getTransactionError() : ?TransactionErrorInterface;

    /**
     * Determine if the transaction is currently being processed.
     *
     * @return bool Returns true if the transaction is being processed, false otherwise.
     */
    public function in(TransactionInterface $transaction) : bool;

    /**
     * Process the transaction and return the result.
     *
     * @param ClientInterface $client The HTTP client to be used for sending requests to the payment gateway.
     * @param LoggerInterface|null $logger
     * An optional logger for logging any relevant information during transaction processing.
     * @return TransactionProcessorInterface
     * Returns the instance of the transaction processor after processing the transaction,
     * which may contain updated information about the transaction status, response, or error details.
     */
    public function process(
        ClientInterface $client,
        ?LoggerInterface $logger = null
    ) : TransactionProcessorInterface;
}
