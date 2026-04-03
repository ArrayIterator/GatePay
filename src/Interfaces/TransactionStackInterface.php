<?php
declare(strict_types=1);

namespace GatePay\Core\Interfaces;

use GatePay\Core\Enum\TransactionState;
use Psr\Log\LoggerInterface;

/**
 * # Transaction Stack for real world capabilities.
 * This interface defines a structured flow for handling transactions with a payment gateway,
 *
 * ---------------------------------------
 *      !WHO START === WHO END!
 * ---------------------------------------
 *
 * 1. stack `begin` : Prepare for data integrity checks, logging,
 *      or any necessary setup before the transaction is processed.
 * 2. stack `then` : Handle the result of the transaction processing, allowing for any necessary actions
 *      to be taken based on the success or failure of the transaction.
 * 3. stack `catch` : Handle any exceptions or errors that may occur during the transaction processing,
 *      allowing for proper error handling and logging.
 * 4. stack `finally` : Perform any final steps that need to be taken after the transaction processing is complete,
 *      regardless of the outcome, such as logging the final result or performing cleanup tasks
 *
 * On every method can be used as Hard Deny with Throw if integrity checks fail,
 * or any other reason that should prevent the transaction from being processed.
 * This allows for a flexible and robust transaction processing flow,
 * ensuring that all necessary steps are taken to maintain data integrity,
 * handle errors effectively, and perform any required finalization tasks after the transaction is
 */
interface TransactionStackInterface
{
    /**
     * Get the current state of the transaction.
     *
     * @return TransactionState
     */
    public function getState() : TransactionState;

    /**
     * Ensure that logging is enabled for this transaction stack.
     * This method can be used to indicate that logging should be performed for this transaction stack,
     * allowing for better traceability and debugging during the transaction processing.
     *
     * @return bool Returns true if logging is enabled for this transaction stack, false otherwise.
     */
    public function isEnableLogging(): bool;

    /**
     * Set whether logging should be enabled for this transaction stack.
     * This method can be used to enable or disable logging for this transaction stack,
     * allowing for better control over the amount of logging performed during the transaction processing.
     *
     * @param bool $enableLogging Set to true to enable logging for this transaction stack, false to disable it.
     */
    public function setEnableLogging(bool $enableLogging): void;

    /**
     * The `GateKeeper`  before the transaction is processed,
     * allowing for any necessary setup, validation,
     * or pre-processing steps to be performed based on the transaction result information.
     *
     * This method is called before the transaction is processed by the payment gateway.
     * It allows for any necessary setup, validation, or pre-processing steps to be performed
     * based on the transaction result information.
     *
     * @param TransactionProcessorInterface $processor
     *  The transaction result information that can be used for setup or validation before processing.
     * @param LoggerInterface|null $logger An optional logger that can be used to
     * log information about the transaction processing,
     * such as the transaction details, any validation checks performed, or any other relevant information.
     *
     * @note
     * This method can be used to perform any necessary checks
     * or preparations before the transaction is sent to the payment gateway,
     * such as validating the transaction data, logging the transaction details, or setting up any required.
     * Can be thrown if integrity checks fail
     * or if there are any issues that should prevent the transaction from being processed.
     */
    public function begin(
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): void;

    /**
     * This method is called after the transaction has been processed,
     * regardless of the outcome (success or failure).
     * It allows for any necessary cleanup, logging,
     * or finalization steps to be performed based on the result of the transaction.
     *
     * @param TransactionResponseInterface $result The result of the transaction processing,
     * including the transaction details, status, and any response data from the payment gateway.
     * @param TransactionProcessorInterface $processor The transaction processor containing
     * the final state of the transaction,
     * containing details about success, failure, and any relevant data.
     * @param LoggerInterface|null $logger An optional logger that can be used
     * to log information about the transaction processing,
     * such as the transaction result, any errors that occurred, or any other relevant details.
     *
     * @note
     * This method can be used to perform any necessary actions after the transaction has been processed,
     * such as logging the transaction result, updating the transaction status in a database,
     * or performing any necessary cleanup tasks based on the outcome of the transaction.
     */
    public function then(
        TransactionResponseInterface $result,
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): void;

    /**
     * This method is called if an error occurs during the transaction processing.
     * It allows for handling any exceptions or errors that may arise,
     * such as logging the error, performing cleanup, or taking corrective actions.
     *
     * @param TransactionErrorInterface $error The error information related to the transaction failure,
     * @param TransactionProcessorInterface $processor The transaction processor containing
     * the state of the transaction at the time of the error, which may include details about the transaction,
     * including the exception that caused the failure and any relevant details.
     *
     * @note
     * This method can be used to handle any errors that occur during the transaction processing,
     * allowing for proper error handling and logging, as well as any necessary cleanup or corrective actions
     */
    public function catch(
        TransactionErrorInterface $error,
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): void;

    /**
     * This method is called after the transaction processing is complete,
     * regardless of whether it was successful or if an error occurred.
     * It allows for any final steps to be taken, such as logging the final outcome,
     * performing cleanup, or executing any necessary actions based on the final state of the transaction.
     *
     * @param TransactionProcessorInterface $processor The transaction
     * processor containing the final state of the transaction,
     * including the transaction details, response, and any errors that may have occurred.
     * @param LoggerInterface|null $logger An optional logger that can be used
     * to log information about the final state of the transaction,
     * such as the final result, any errors that occurred, or any other relevant details.
     *
     * @note
     * This method can be used to perform any necessary finalization steps after
     * the transaction processing is complete,
     * such as logging the final result, updating the transaction status in a database, or performing
     * any necessary cleanup tasks regardless of the outcome of the transaction.
     * It ensures that all necessary steps are taken to finalize the transaction processing flow,
     * even if an error occurred during processing.
     */
    public function finally(TransactionProcessorInterface $processor, ?LoggerInterface $logger = null): void;

    /**
     * Get data associated with the transaction that may be needed for processing or logging purposes.
     * This method can return any relevant information about the transaction, such as the amount, currency,
     * customer details, or any other metadata that may be useful for the payment gateway or for internal tracking.
     *
     * We don't use method name `getData` cause it was different strategy for data management,
     * and it can be confusing with the `parameters` property of the transaction.
     *
     * @return TransactionResultDataInterface|null Returns an object
     * `    containing transaction data, or null if no additional data is available.
     */
    public function getTransactionResultData(): ?TransactionResultDataInterface;
}
