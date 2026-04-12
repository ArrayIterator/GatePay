<?php
declare(strict_types=1);

namespace GatePay\Core\Interfaces;

use GatePay\Core\Enum\GatewayAction;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * This interface defines the contract for payment adapters that can be used with the PaymentGateway.
 * Each adapter must provide methods to retrieve its name, sandbox URL, and production URL.
 */
interface GatewayInterface
{
    /**
     * Get the name of the payment gateway.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the URL for the sandbox environment of the payment gateway.
     *
     * @return string|null Returns the sandbox URL if available, or null if not applicable.
     */
    public function getDescription(): ?string;

    /**
     * Get the URL for the production environment of the payment gateway.
     *
     * @return string|null Returns the production URL if available, or null if not applicable.
     */
    public function getVersion(): ?string;

    /**
     * Check if the payment gateway supports a sandbox environment.
     *
     * @return bool Returns true if the gateway supports sandbox mode, false otherwise.
     */
    public function isSupportSandbox(): bool;

    /**
     * Check if the payment gateway supports a production environment.
     *
     * @return bool Returns true if the gateway supports production mode, false otherwise.
     */
    public function isSupportProduction(): bool;

    /**
     * Get the URL for the sandbox environment of the payment gateway.
     *
     * @return string Returns the sandbox URL for testing purposes.
     * @throws \GatePay\Core\Exceptions\UnsupportedModeException<"sandbox">
     * If the gateway does not support sandbox mode.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getSandboxUrl(): string;

    /**
     * Get the URL for the production environment of the payment gateway.
     *
     * @return string Returns the production URL for live transactions.
     * @throws \GatePay\Core\Exceptions\UnsupportedModeException<"production">
     * If the gateway does not support production mode.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getProductionUrl(): string;

    /**
     * Get the list of supported payment actions for this adapter.
     *
     * @return array<GatewayAction>
     *     Returns an array of supported payment actions that this adapter can handle.
     */
    public function getSupportedActions(): array;

    /**
     * Check if a specific payment action is supported by this adapter.
     *
     * @param GatewayAction $action
     * @return bool Returns true if the action is supported, false otherwise.
     */
    public function hasAction(GatewayAction $action): bool;

    /**
     * Get the action handler for a specific payment action.
     * @template T of GatewayAction
     * @param T $action
     * @param array<array-key, mixed> $parameters
     *      Optional parameters that may be needed to retrieve the action handler.
     * @return GatewayActionInterface<T> Returns the action handler for the specified action.
     * @throws \GatePay\Core\Exceptions\UnsupportedActionException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getAction(GatewayAction $action, array $parameters = []): GatewayActionInterface;

    /**
     * Process a transaction using the appropriate action handler for the specified transaction.
     * The method takes a TransactionInterface object,
     * a RequestFactoryInterface&StreamFactoryInterface for creating HTTP requests,
     * @param TransactionInterface $transaction
     *     The transaction to be processed, which contains all the necessary information for processing the payment.
     * @param RequestFactoryInterface&StreamFactoryInterface $factory
     *      The request factory to be used for creating the HTTP request for the transaction.
     * @param ClientInterface $client
     *      The HTTP client to be used for sending the request to the payment gateway.
     * @return TransactionProcessorInterface
     *      Processes the transaction and returns
     *      a TransactionProcessorInterface that contains the result of the processing.
     * @throws \Throwable
     * @throws \GatePay\Core\Exceptions\UnsupportedActionException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function process(
        TransactionInterface                           $transaction,
        RequestFactoryInterface&StreamFactoryInterface $factory,
        ClientInterface                                $client
    ): TransactionProcessorInterface;
}
