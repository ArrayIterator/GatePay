<?php
declare(strict_types=1);

namespace GatePay\Core\Interfaces;

use GatePay\Core\Enum\GatewayAction;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * This interface serves as a marker for payment actions that can be performed by payment adapters.
 * It can be implemented by enums or classes that represent specific payment actions (e.g., charge, refund).
 *
 * @template Action of GatewayAction
 */
interface GatewayActionInterface
{
    /**
     * Constructor for the GatewayActionInterface.
     *
     * @param GatewayInterface $gateway The payment gateway associated with this action.
     */
    public function __construct(GatewayInterface $gateway);

    /**
     * Get the payment gateway associated with this action.
     *
     * @return GatewayInterface The payment gateway that supports this action.
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
     * @param GatewayInterface $gateway The payment gateway for which the request is being created.
     * @param TransactionInterface $transaction The transaction for which the request is being created.
     * @param RequestFactoryInterface $requestFactory The factory to use for creating the HTTP request.
     * @param LoggerInterface|null $logger Optional logger for logging any relevant information during request creation
     * @return RequestInterface The HTTP request to be sent to the payment gateway for processing this action.
     *
     * @throws \GatePay\Core\Exceptions\UnsupportedActionException
     *
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function createRequest(
        GatewayInterface $gateway,
        TransactionInterface $transaction,
        RequestFactoryInterface $requestFactory,
        ?LoggerInterface $logger = null
    ) : RequestInterface;
}
