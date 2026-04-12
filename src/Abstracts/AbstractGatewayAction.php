<?php
declare(strict_types=1);

namespace GatePay\Core\Abstracts;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Interfaces\GatewayActionInterface;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use const E_WARNING;

/**
 * AbstractGatewayAction provides a base implementation of the GatewayActionInterface.
 * It includes common functionality for handling a specific payment action and creating requests for transactions.
 * Concrete gateway actions can extend this class and implement the specific details for each action.
 *
 * @template Action of GatewayAction
 * @template-implements GatewayActionInterface<Action>
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
     * Asserts that the given transaction is processable for this action and that the provided gateway is supported.
     * If the transaction is not processable or if the gateway is not supported,
     * an UnsupportedActionException is thrown.
     *
     * - The action should only be processable for transactions that have the same action type as this action.
     * - The provided gateway must be the same as the one associated with this action.
     * This method ensures that the action is used correctly with the appropriate transactions and gateways,
     * and prevents misuse of the action with unsupported transactions or gateways.
     *
     * @param GatewayInterface $gateway The payment gateway to check for support.
     * @param TransactionInterface $transaction The transaction to check for processability.
     *
     * @throws UnsupportedActionException
     *      If the transaction is not processable or if the gateway is not supported.
     *      The error code is set to E_WARNING to indicate that this is a warning-level issue,
     *      as it indicates a misuse of the action rather than a critical error.
     */
    protected function assertProcessable(
        GatewayInterface $gateway,
        TransactionInterface $transaction
    ) : void {
        // check if the provided gateway is the same as the one associated with this action
        // this will make secure that the action is only used with the
        // correct gateway and prevents misuse of the action with unsupported gateways.
        if ($gateway !== $this->getGateway()) {
            throw new UnsupportedActionException(
                $gateway,
                $transaction->getAction(),
                'The provided gateway is mismatched with the action\'s gateway.',
                code: E_WARNING
            );
        }
        // validate action type of the transaction against the action type of this gateway action
        if ($transaction->getAction() !== $this->getAction()) {
            throw new UnsupportedActionException(
                $gateway,
                $transaction->getAction(),
                'The transaction action does not match the action type of this gateway action.',
                code: E_WARNING
            );
        }
        // check if the transaction is processable for this action using the isProcessable method
        if (!$this->isProcessable($transaction)) {
            throw new UnsupportedActionException(
                $this->getGateway(),
                $this->getAction(),
                'The transaction is not processable for this action.',
                code: E_WARNING
            );
        }
    }

    /**
     * @inheritdoc
     */
    abstract public function getAction(): GatewayAction;

    /**
     * @inheritdoc
     */
    abstract public function createRequest(
        GatewayInterface                               $gateway,
        TransactionInterface                           $transaction,
        RequestFactoryInterface&StreamFactoryInterface $factory,
        ?LoggerInterface                               $logger = null
    ): RequestInterface;
}
