<?php
declare(strict_types=1);

namespace GatePay\Core\Enum;

enum TransactionStatus: string
{
    case PROCESSING = 'PROCESSING';

    case PENDING = 'PENDING'; // processing, awaiting confirmation, etc.

    case SUCCESS = 'SUCCESS';

    case FAILED = 'FAILED';

    case CANCELED = 'CANCELED';

    /**
     * @return bool Returns true if the transaction is marked as successful, false otherwise.
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * @return bool Returns true if the transaction is marked as processing, false otherwise.
     */
    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * @return bool Returns true if the transaction is marked as failed, false otherwise.
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * @return bool Returns true if the transaction is marked as pending, false otherwise.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * @return bool Returns true if the transaction is marked as canceled, false otherwise.
     */
    public function isCanceled(): bool
    {
        return $this === self::CANCELED;
    }

    /**
     * @return bool Returns true if the transaction is marked as an error (either failed or canceled),
     * false otherwise.
     */
    public function isMarkedAsError(): bool
    {
        return $this === self::FAILED || $this === self::CANCELED;
    }

    /**
     * @return bool Returns true if the transaction is marked as successful or failed (i.e., it has been processed),
     * false otherwise.
     */
    public function isProcessed(): bool
    {
        return $this->isSuccess() || $this->isFailed() || $this->isCanceled();
    }
}
