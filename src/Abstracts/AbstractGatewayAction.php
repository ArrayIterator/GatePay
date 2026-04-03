<?php
declare(strict_types=1);

namespace GatePay\Core\Abstracts;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Interfaces\GatewayActionInterface;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * AbstractGatewayAction provides a base implementation of the GatewayActionInterface.
 * It includes common functionality for handling a specific payment action and creating requests for transactions.
 * Concrete gateway actions can extend this class and implement the specific details for each action.
 *
 * @template Gateway of GatewayInterface
 */
abstract class AbstractGatewayAction implements GatewayActionInterface
{
    /**
     * AbstractGatewayAction constructor.
     *
     * @param Gateway $gateway The payment gateway associated with this action.
     */
    public function __construct(public readonly GatewayInterface $gateway)
    {
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
    public function isProcessable(TransactionInterface $transaction): bool
    {
        return $transaction->getAction() === $this->getAction();
    }

    /**
     * @inheritdoc
     */
    abstract public function getAction(): GatewayAction;

    /**
     * @inheritdoc
     */
    abstract public function createRequest(
        TransactionInterface $transaction,
        RequestFactoryInterface $requestFactory,
        ?LoggerInterface $logger = null
    ): RequestInterface;
}
