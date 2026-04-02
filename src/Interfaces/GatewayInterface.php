<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Interfaces;

use ArrayIterator\GatePay\Enum\GatewayAction;
use Psr\Http\Client\ClientInterface;

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
     * @throws \ArrayIterator\GatePay\Exceptions\UnsupportedModeException<"sandbox">
     * If the gateway does not support sandbox mode.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getSandboxUrl(): string;

    /**
     * Get the URL for the production environment of the payment gateway.
     *
     * @return string Returns the production URL for live transactions.
     * @throws \ArrayIterator\GatePay\Exceptions\UnsupportedModeException<"production">
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
     *
     * @param GatewayAction $action
     * @param array<array-key, mixed> $parameters
     *      Optional parameters that may be needed to retrieve the action handler.
     * @return GatewayActionInterface Returns the action handler for the specified action.
     * @throws \ArrayIterator\GatePay\Exceptions\UnsupportedActionException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getAction(GatewayAction $action, array $parameters = []): GatewayActionInterface;

    /**
     * @param TransactionInterface $transaction
     * @param ClientInterface $client
     * @return TransactionProcessorInterface
     * @throws \Throwable
     * @throws \ArrayIterator\GatePay\Exceptions\UnsupportedActionException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function process(
        TransactionInterface $transaction,
        ClientInterface      $client
    ): TransactionProcessorInterface;
}
