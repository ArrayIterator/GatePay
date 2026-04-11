<?php
declare(strict_types=1);

namespace GatePay\Core;

use GatePay\Core\Enum\SourceType;
use GatePay\Core\Enum\TransactionState;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Interfaces\TransactionResponseInterface;
use GatePay\Core\Interfaces\TransactionResultDataInterface;
use GatePay\Core\Interfaces\TransactionStackInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function in_array;
use function is_array;
use function preg_match;
use function time;

/**
 * DefaultTransactionStack is a default implementation of the TransactionStackInterface.
 * It manages the state of a transaction processing flow,
 * including handling the beginning of the transaction,
 * processing successful responses, catching errors,
 * and finalizing the transaction by extracting result data from the response.
 *
 * @template TKey of array-key
 * @template TValue
 * @template-implements TransactionStackInterface<TKey, TValue>
 */
class TransactionStack implements TransactionStackInterface
{
    /**
     * @var TransactionResponseInterface|null $response
     * The raw response from the payment gateway after processing the transaction.
     */
    private ?TransactionResponseInterface $transactionResponse = null;

    /**
     * @var TransactionState $state The current state of the transaction processing.
     * It can be one of the following states:
     * - PENDING: The transaction is pending and has not started processing yet.
     * - BEGIN: The transaction has started processing.
     * - SUCCESS: The transaction has been processed successfully.
     * - ERROR: An error occurred during the transaction processing.
     * - FINAL: The transaction processing is complete, regardless of success or error.
     */
    private TransactionState $state = TransactionState::PENDING;

    /**
     * @var TransactionResultDataInterface<TKey, TValue>|null $transactionResultData
     * The result data of the transaction processing,
     * which may include additional information about the transaction outcome.
     */
    private ?TransactionResultDataInterface $transactionResultData = null;

    /**
     * @var bool $enableLogging A flag to enable or disable logging of transaction processing events.
     * When set to true, the stack will log relevant information about the transaction processing,
     * such as state transitions, errors, and other important events.
     * This can be useful for debugging and monitoring the transaction processing flow.
     */
    protected bool $enableLogging = true;

    /**
     * @var int $timestamp The timestamp of when the transaction stack was begin!.
     * This can be used for tracking the timing of transaction processing events or for logging purposes.
     */
    private int $timestamp = 0;

    /**
     * Constructor
     *
     * @param Transaction $transaction
     */
    public function __construct(private readonly Transaction $transaction)
    {
    }

    /**
     * @inheritdoc
     */
    public function getState(): TransactionState
    {
        return $this->state;
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @inheritdoc
     */
    public function isEnableLogging(): bool
    {
        return $this->enableLogging;
    }

    /**
     * @inheritdoc
     */
    public function setEnableLogging(bool $enableLogging): void
    {
        $this->enableLogging = $enableLogging;
    }

    /**
     * Check if the transaction can be processed based on the current state and the expected previous state.
     *
     * @param TransactionState $processState The state that the transaction is trying to transition to.
     * @param TransactionState|array<array-key, TransactionState> $prevState
     *      The expected previous state(s) that the transaction should be in
     * before transitioning to the new state.
     * @param TransactionProcessorInterface $processor The transaction processor containing the transaction details.
     * @param LoggerInterface|null $logger An optional logger for logging any warnings or errors during the process.
     *
     * @return bool Returns true if the transaction can be processed, false otherwise.
     */
    private function isProcessable(
        TransactionState              $processState,
        TransactionState|array        $prevState,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger
    ): bool {
        $transaction = $processor->getTransaction();
        if ($transaction !== $this->transaction) {
            // skip if the processor is not the same as the transaction
            $this->isEnableLogging() && $logger?->warning(
                'TransactionProcessor does not match the transaction in the stack. Skipping.',
                [
                    'processor_transaction' => $processor->getTransaction(),
                    'stack_transaction' => $this->transaction,
                    'state' => $this->state,
                ]
            );
            return false;
        }
        return $transaction->getState() === $processState && (
            is_array($prevState) ? in_array($this->state, $prevState) : $this->state === $prevState
        );
    }

    /**
     * @inheritdoc
     */
    final public function begin(TransactionProcessorInterface $processor, ?LoggerInterface $logger = null): void
    {
        if (!$this->isProcessable(
            TransactionState::BEGIN,
            TransactionState::PENDING,
            $processor,
            $logger
        )) {
            return;
        }
        $this->timestamp = time();
        $this->state = TransactionState::BEGIN;
        $this->onStart($processor, $logger);
    }

    /**
     * @inheritdoc
     */
    final public function then(
        TransactionResponseInterface  $result,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ): void {
        if (!$this->isProcessable(
            TransactionState::SUCCESS,
            TransactionState::BEGIN,
            $processor,
            $logger
        )) {
            return;
        }
        $this->state = TransactionState::SUCCESS;
        $this->transactionResponse = $result;
        $this->onSuccess($result, $processor, $logger);
    }

    /**
     * @inheritdoc
     */
    final public function catch(
        TransactionErrorInterface     $error,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ): void {
        if (!$this->isProcessable(
            TransactionState::ERROR,
            TransactionState::BEGIN,
            $processor,
            $logger
        )) {
            return;
        }
        $this->state = TransactionState::ERROR;
        $this->onError($error, $processor, $logger);
    }

    /**
     * @inheritdoc
     */
    public function finally(TransactionProcessorInterface $processor, ?LoggerInterface $logger = null): void
    {
        if (!$this->isProcessable(
            TransactionState::FINAL,
            [TransactionState::SUCCESS, TransactionState::ERROR],
            $processor,
            $logger
        )) {
            return;
        }
        $this->state = TransactionState::FINAL;
        $this->transactionResponse = $processor->getTransactionResponse()
            ?? $this->transactionResponse;
        $response = $this->transactionResponse?->getResponse();
        if (!$response) {
            return;
        }
        $this->transactionResultData = $this->parseDataFromResponse($response, $processor, $logger);
        $this->onFinish($processor, $logger);
    }

    /**
     * Check if the response is a JSON response based on the Content-Type header.
     *
     * @param ResponseInterface $response The HTTP response to check.
     * @return ?SourceType Returns true if the response is a JSON response,
     * false otherwise.
     */
    protected function detectSourceType(ResponseInterface $response): ?SourceType
    {
        $contentType = $response->getHeaderLine('Content-Type');
        if ($contentType === '') {
            return null;
        }
        if (preg_match('~application/([^/+;]\+)?json~i', $contentType)) {
            return SourceType::JSON;
        }
        if (preg_match('~application/([^/+;]\+)?xml~i', $contentType)) {
            return SourceType::XML;
        }
        // it is impossible payment gateway returning YAML fil!e, so we can skip it for now
        // if (preg_match('~/([^/+;]\+)?ya?ml\s*(;|$)~i', $contentType)) {
        //    return SourceType::YAML;
        // }
        return null;
    }

    /**
     * @inheritdoc
     * @return TransactionResultDataInterface<TKey, TValue>
     */
    final public function getTransactionResultData(): ?TransactionResultDataInterface
    {
        return $this->transactionResultData;
    }

    /**
     * Parse the response body to extract transaction result data based on the detected source type.
     *
     * @param ResponseInterface $response The HTTP response containing the body to parse.
     * @param TransactionProcessorInterface $processor The transaction processor containing the transaction details.
     * @param LoggerInterface|null $logger An optional logger that can be used to
     * log any errors or warnings during the parsing process,
     * such as issues with reading the response body or decoding it as JSON, XML, or YAML.
     *
     * @return ?TransactionResultDataInterface<TKey, TValue>
     * Returns the parsed transaction result data if successful, null otherwise.
     *
     * @abstract
     */
    protected function parseDataFromResponse(
        ResponseInterface             $response,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ): ?TransactionResultDataInterface {
        if ($processor->getTransaction() !== $this->transaction) {
            // skip if the processor is not the same as the transaction
            $this->isEnableLogging() && $logger?->warning(
                'TransactionProcessor does not match the transaction in the stack. Skipping parsing response data.',
                [
                    'processor_transaction' => $processor->getTransaction(),
                    'stack_transaction' => $this->transaction,
                    'state' => $this->state,
                ]
            );
            return null;
        }
        $sourceType = $this->detectSourceType($response);
        if ($sourceType === null) {
            return null;
        }
        try {
            $content = $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->isEnableLogging() && $logger?->error(
                'Failed to clone or rewind the response body. The transaction result data may be incomplete.',
                [
                    'exception' => $e,
                    'response_body' => $response->getBody(),
                ]
            );
            return null;
        }
        try {
            return match ($sourceType) {
                SourceType::JSON => TransactionResultData::fromJson($content)->freeze(),
                SourceType::XML => TransactionResultData::fromXML($content)->freeze(),
                default => null,
            };
        } catch (Throwable $e) {
            $this->isEnableLogging() && $logger?->error(
                'Failed to decode the response body as JSON. The transaction result data may be incomplete.',
                [
                    'exception' => $e,
                    'response_body_content' => $content,
                ]
            );
            return null;
        }
    }

    /**
     * This method is called when the transaction processing begins.
     * It allows for any necessary setup, logging,
     * or initialization steps to be performed before the transaction is processed.
     *
     * @param TransactionProcessorInterface $processor The transaction processor containing the transaction details.
     * @param LoggerInterface|null $logger An optional
     * logger that can be used to log information about the transaction processing,
     * such as the transaction details, any relevant information,
     * or any other necessary logging during the start of the transaction processing.
     * @return void
     *
     * @note
     * This method can be used to perform any necessary actions before the transaction is processed,
     * such as logging the transaction details, initializing any necessary resources,
     * or performing any setup tasks required for the transaction processing.
     *
     * @abstract
     */
    protected function onStart(
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ) : void {
    }

    /**
     * This method is called when the transaction processing is successful.
     * It allows for handling the successful response from the payment gateway,
     * such as logging the success, updating the transaction status,
     * or performing any necessary actions based on the successful outcome of the transaction.
     *
     * @param TransactionResponseInterface $result The result of the successful transaction processing,
     * including the transaction details, status, and any response data from the payment gateway.
     * @param TransactionProcessorInterface $processor The transaction processor containing
     * the final state of the transaction, including details about success and any relevant data.
     * @param LoggerInterface|null $logger An optional logger that can be used to log information about
     * the successful transaction processing, such as the transaction result, any relevant details,
     * or any other necessary logging during the handling of a successful transaction.
     * @return void
     *
     * @note
     * This method can be used to perform any necessary actions after a successful transaction processing,
     * such as logging the success, updating the transaction status in a database,
     * or performing any necessary tasks based on the successful outcome of the transaction.
     *
     * @abstract
     */
    protected function onSuccess(
        TransactionResponseInterface  $result,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ) : void {
    }

    /**
     * This method is called when an error occurs during the transaction processing.
     * It allows for handling any exceptions or errors that may arise,
     * such as logging the error, performing cleanup, or taking corrective actions.
     *
     * @param TransactionErrorInterface $error The error information related to the transaction failure,
     * @param TransactionProcessorInterface $processor The transaction processor containing
     * the state of the transaction at the time of the error, which may include details about the transaction,
     * including the exception that caused the failure and any relevant details.
     * @param LoggerInterface|null $logger An optional logger that can be used to log information about
     * the error that occurred during the transaction processing, such as the error details, any relevant information,
     * or any other necessary logging during the handling of a failed transaction.
     * @return void
     *
     * @note
     * This method can be used to handle any errors that occur during the transaction processing,
     * allowing for proper error handling and logging, as well as any necessary cleanup or corrective actions
     * based on the nature of the error and its impact on the transaction processing flow.
     *
     * @abstract
     */
    protected function onError(
        TransactionErrorInterface     $error,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ) : void {
    }

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
     * allowing for proper handling and logging of the final state of the transaction.
     * @return void
     * @abstract
     */
    protected function onFinish(
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ) : void {
    }
}
