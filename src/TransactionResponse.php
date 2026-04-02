<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay;

use ArrayIterator\GatePay\Enum\TransactionStatus;
use ArrayIterator\GatePay\Interfaces\TransactionInterface;
use ArrayIterator\GatePay\Interfaces\TransactionResponseInterface;
use Psr\Http\Message\ResponseInterface;

class TransactionResponse implements TransactionResponseInterface
{
    /**
     * TransactionResponse constructor.
     * This class represents the response of a transaction processed by a payment gateway.
     * It contains the transaction details, the status of the transaction,
     * and the raw response from the payment gateway.
     *
     * @param TransactionInterface $transaction The transaction associated with this response.
     * @param TransactionStatus $status The status of the transaction after processing.
     * @param ResponseInterface $response The raw response from the payment gateway.
     */
    public function __construct(
        public readonly TransactionInterface $transaction,
        public readonly TransactionStatus    $status,
        public readonly ResponseInterface    $response
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
    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
