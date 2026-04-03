<?php
declare(strict_types=1);

namespace GatePay\Core;

use GatePay\Core\Enum\TransactionStatus;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Interfaces\TransactionResponseInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The transaction processor
 */
class TransactionProcessor implements TransactionProcessorInterface
{
    /**
     * @var TransactionStatus $status The current status of the transaction.
     */
    private TransactionStatus $status;

    /**
     * @var TransactionResponseInterface|null $response
     * The response from the payment gateway after processing the transaction.
     * This will be null if the transaction has not been processed yet or if there was an error during processing.
     */
    private ?TransactionResponseInterface $response = null;

    /**
     * @var TransactionErrorInterface|null $error The error information if the transaction failed.
     * This will be null if the transaction was successful or if it has not been processed yet.
     */
    private ?TransactionErrorInterface $error = null;

    /**
     * TransactionProcessor constructor.
     *
     * @param GatewayInterface $gateway The payment gateway that will process the transaction.
     * @param TransactionInterface $transaction The transaction to be processed.
     * @param RequestInterface $request The original request that initiated the transaction.
     */
    public function __construct(
        public readonly GatewayInterface $gateway,
        public readonly TransactionInterface $transaction,
        public readonly RequestInterface $request
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getTransaction(): TransactionInterface
    {
        return $this->transaction;
    }

    /**
     * @inheritdoc
     */
    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    /**
     * @inheritdoc
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionStatus(): TransactionStatus
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionResponse(): ?TransactionResponseInterface
    {
        return $this->response;
    }

    /**
     * @inheritdoc
     */
    public function getTransactionError(): ?TransactionErrorInterface
    {
        return $this->error;
    }

    /**
     * @inheritdoc
     */
    public function in(TransactionInterface $transaction): bool
    {
        return $this->getTransaction() === $transaction;
    }

    /**
     * @inheritdoc
     */
    public function process(
        ClientInterface $client,
        ?LoggerInterface $logger = null
    ) : TransactionProcessorInterface {
        if ($this->getTransactionStatus()->isPending()) {
            return $this;
        }
        try {
            $this->status = TransactionStatus::PROCESSING;
            try {
                $this->transaction->begin($this);
            } catch (Throwable $e) {
                $this->status = TransactionStatus::CANCELED;
                $this->error = TransactionError::createFromTransaction(
                    $this->transaction,
                    $e
                );
                $this->transaction->isEnableLogging() && $logger?->warning(
                    'Error during transaction begin: ' . $e->getMessage(),
                    [
                        'transaction_id' => $this->transaction->getTransactionId(),
                        'gateway' => $this->gateway->getName(),
                        'action' => $this->transaction->getAction()->value,
                    ]
                );
                try {
                    $this->transaction->catch($this->error, $this, $logger);
                } catch (Throwable $e) {
                    $this->transaction->isEnableLogging() && $logger?->warning(
                        'Error during transaction error handling: ' . $e->getMessage(),
                        [
                            'transaction_id' => $this->transaction->getTransactionId(),
                            'gateway' => $this->gateway->getName(),
                            'action' => $this->transaction->getAction()->value,
                        ]
                    );
                    // If an error occurs while handling the initial error, we can log it or handle it as needed.
                    // For now, we'll just ignore it to avoid overriding the original error.
                }
                return $this;
            }
            try {
                $response = $client->sendRequest($this->request);
            } catch (Throwable $e) {
                $this->status = TransactionStatus::FAILED;
                $this->error = TransactionError::createFromTransaction(
                    $this->transaction,
                    $e
                );
                $this->transaction->isEnableLogging() && $logger?->warning(
                    'Error during transaction processing: ' . $e->getMessage(),
                    [
                        'transaction_id' => $this->transaction->getTransactionId(),
                        'gateway' => $this->gateway->getName(),
                        'action' => $this->transaction->getAction()->value,
                    ]
                );
                try {
                    $this->transaction->catch($this->error, $this);
                } catch (Throwable $e) {
                    $this->transaction->isEnableLogging() && $logger?->warning(
                        'Error during transaction error handling: ' . $e->getMessage(),
                        [
                            'transaction_id' => $this->transaction->getTransactionId(),
                            'gateway' => $this->gateway->getName(),
                            'action' => $this->transaction->getAction()->value,
                        ]
                    );
                    // If an error occurs while handling the processing error, we can log it or handle it as needed.
                    // For now, we'll just ignore it to avoid overriding the original error.
                }
                return $this;
            }
            $this->status = TransactionStatus::SUCCESS;
            $this->response = new TransactionResponse(
                transaction: $this->transaction,
                status: TransactionStatus::SUCCESS, // Default to success, will be updated based on response
                response: $response
            );
            try {
                $this->transaction->then($this->response, $this);
            } catch (Throwable $e) {
                $this->transaction->isEnableLogging() && $logger?->warning(
                    'Error during transaction response handling: ' . $e->getMessage(),
                    [
                        'transaction_id' => $this->transaction->getTransactionId(),
                        'gateway' => $this->gateway->getName(),
                        'action' => $this->transaction->getAction()->value,
                    ]
                );
                // If an error occurs while handling the response, we can log it or handle it as needed.
            }
        } finally {
            try {
                $this->transaction->finally($this);
            } catch (Throwable $e) {
                $this->transaction->isEnableLogging() && $logger?->warning(
                    'Error during transaction finalization: ' . $e->getMessage(),
                    [
                        'transaction_id' => $this->transaction->getTransactionId(),
                        'gateway' => $this->gateway->getName(),
                        'action' => $this->transaction->getAction()->value,
                    ]
                );
                // If an error occurs during finalization, we can log it or handle it as needed.
            }
        }
        return $this;
    }
}
