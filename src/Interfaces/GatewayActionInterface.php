<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Interfaces;

use ArrayIterator\GatePay\Enum\GatewayAction;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * This interface serves as a marker for payment actions that can be performed by payment adapters.
 * It can be implemented by enums or classes that represent specific payment actions (e.g., charge, refund).
 *
 * @template Action of GatewayAction
 * @template Gateway of GatewayInterface
 */
interface GatewayActionInterface
{
    /**
     * Constructor for the GatewayActionInterface.
     *
     * @param Gateway $gateway The payment gateway associated with this action.
     */
    public function __construct(GatewayInterface $gateway);

    /**
     * Get the payment gateway associated with this action.
     *
     * @return Gateway The payment gateway that supports this action.
     */
    public function getGateway(): GatewayInterface;

    /**
     * Get the specific payment action represented by this interface.
     *
     * @return Action
     */
    public function getAction(): GatewayAction;

    /**
     * Determine if this payment action can be processed for a given transaction.
     *
     * @param TransactionInterface $transaction The transaction to check for processability.
     * @return bool
     * Returns true if this action can be processed for the given transaction, false otherwise.
     */
    public function isProcessable(TransactionInterface $transaction): bool;

    /**
     * Create and return the HTTP request that represents this payment action for a given transaction.
     *
     * @param TransactionInterface $transaction The transaction for which the request is being created.
     * @param LoggerInterface|null $logger Optional logger for logging any relevant information during request creation
     * @return RequestInterface The HTTP request to be sent to the payment gateway for processing this action.
     *
     * @throws \ArrayIterator\GatePay\Exceptions\UnsupportedActionException
     *
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function createRequest(
        TransactionInterface $transaction,
        ?LoggerInterface $logger = null
    ) : RequestInterface;
}
