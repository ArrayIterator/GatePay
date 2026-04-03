<?php
declare(strict_types=1);

namespace GatePay\Core;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\TransactionState;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Interfaces\TransactionResponseInterface;
use GatePay\Core\Interfaces\TransactionResultDataInterface;
use GatePay\Core\Interfaces\TransactionStackInterface;
use Psr\Log\LoggerInterface;

/**
 * Transaction class represents a payment transaction that can be processed through a payment gateway.
 * It implements the TransactionInterface and provides methods to manage the transaction lifecycle,
 * including beginning the transaction, handling successful responses, catching errors, and finalizing the transaction.
 */
final class Transaction implements TransactionInterface
{
    /**
     * @var TransactionResponseInterface|null
     * $response The response from the payment gateway after processing the transaction.
     * This will be null if the transaction has not been processed yet or if there was an error during processing.
     */
    private ?TransactionResponseInterface $response = null;

    /**
     * @var TransactionErrorInterface|null
     * $error The error information if the transaction failed.
     * This will be null if the transaction was successful or if it has not been processed yet.
     */
    private ?TransactionErrorInterface $error = null;

    /**
     * @var TransactionState $state The current state of the transaction processing.
     */
    private TransactionState $state = TransactionState::PENDING;

    /**
     * @var TransactionStackInterface $stack transaction stack that can be used to manage
     * the transaction processing flow and handle callbacks for different stages of the transaction lifecycle.
     */
    private readonly TransactionStackInterface $stack;

    /**
     * @var int $timestamp The timestamp when the transaction was created.
     * This can be used for logging, debugging, or tracking purposes to determine when the transaction was initiated.
     */
    private int $timestamp = 0;

    /**
     * Transaction constructor.
     *
     * @param string $transactionId The unique identifier for the transaction.
     * @param GatewayAction $action The action to be performed for this transaction (e.g., charge, refund).
     * @param array $parameters Optional parameters associated with the transaction, such as amount, currency, etc.
     * @param ?TransactionStackInterface $stack An optional transaction stack that can be used to manage
     * the transaction processing flow and handle callbacks for different stages of the transaction lifecycle.
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly GatewayAction $action,
        public readonly array $parameters = [],
        ?TransactionStackInterface $stack = null
    ) {
        // just make sure safe from circular reference
        if (!$stack || $stack === $this) {
            $stack = new TransactionStack($this);
        }
        $this->stack = $stack;
    }

    /**
     * Get inner transaction stack instance.
     *
     * @return TransactionStackInterface
     */
    public function getStack(): TransactionStackInterface
    {
        return $this->stack;
    }

    /**
     * Get the timestamp when the transaction was created.
     *
     * @return int The timestamp of the transaction creation time.
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
        return $this->getStack()->isEnableLogging();
    }

    /**
     * @inheritdoc
     */
    public function setEnableLogging(bool $enableLogging): void
    {
        // forward to stack
        $this->getStack()->setEnableLogging($enableLogging);
    }

    /**
     * @inheritdoc
     */
    public function getState(): TransactionState
    {
        // use current state rather than stack state,
        // because stack state is not updated until the transaction is processed,
        // but the transaction state is updated immediately when the transaction is processed.
        return $this->state;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @inheritdoc
     */
    public function getAction(): GatewayAction
    {
        return $this->action;
    }

    /**
     * @inheritdoc
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionResultData(): ?TransactionResultDataInterface
    {
        return $this->stack->getTransactionResultData();
    }

    /**
     * @inheritdoc
     */
    public function begin(TransactionProcessorInterface $processor, ?LoggerInterface $logger = null): void
    {
        if (!$processor->getTransactionStatus()->isProcessing()) {
            return;
        }
        if ($this->state !== TransactionState::PENDING) {
            return;
        }
        $this->state = TransactionState::BEGIN;
        $this->timestamp = time();
        $this->stack->begin($processor);
    }

    /**
     * @inheritdoc
     */
    public function then(
        TransactionResponseInterface $result,
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): void {
        $status = $processor->getTransactionStatus();
        if (!$status->isProcessed() || !$status->isSuccess()) {
            return;
        }
        if ($this->response !== null) {
            return;
        }
        $this->state = TransactionState::SUCCESS;
        $this->response = $result;
        $this->stack->then($result, $processor, $logger);
    }

    /**
     * @inheritdoc
     */
    public function catch(
        TransactionErrorInterface $error,
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): void {
        if (!$processor->getTransactionStatus()->isMarkedAsError()) {
            return;
        }
        if ($this->error !== null) {
            return;
        }
        $this->state = TransactionState::ERROR;
        $this->error = $error;
        $this->stack->catch($error, $processor, $logger);
    }

    /**
     * @inheritdoc
     */
    public function finally(
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): void {
        $status = $processor->getTransactionStatus();
        if (!$status->isProcessed()) {
            return;
        }
        if ($this->state === TransactionState::FINAL) {
            return;
        }
        $this->state = TransactionState::FINAL;
        $this->stack->finally($processor, $logger);
    }

    /**
     * @inheritdoc
     */
    public function getResponse(): ?TransactionResponseInterface
    {
        return $this->response;
    }

    /**
     * @inheritdoc
     */
    public function getError(): ?TransactionErrorInterface
    {
        return $this->error;
    }
}
