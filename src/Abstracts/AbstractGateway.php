<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Abstracts;

use ArrayIterator\GatePay\Enum\GatewayAction;
use ArrayIterator\GatePay\Exceptions\UnsupportedActionException;
use ArrayIterator\GatePay\Exceptions\UnsupportedModeException;
use ArrayIterator\GatePay\Interfaces\GatewayActionInterface;
use ArrayIterator\GatePay\Interfaces\GatewayInterface;
use ArrayIterator\GatePay\Interfaces\TransactionInterface;
use ArrayIterator\GatePay\Interfaces\TransactionProcessorInterface;
use ArrayIterator\GatePay\TransactionProcessor;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function get_class;
use function is_object;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function str_replace;
use function strrpos;
use function strtoupper;
use function substr;
use function trim;

/**
 * AbstractGateway provides a base implementation of the GatewayInterface.
 * It includes common functionality for handling payment actions and processing transactions.
 * Concrete payment gateways can extend this class and implement the specific details for each action.
 */
abstract class AbstractGateway implements GatewayInterface
{
    /**
     * @var string $name The name of the payment gateway.
     * If not set, it will be derived from the class name.
     */
    protected string $name;

    /**
     * @var string|null $description An optional description of the payment gateway.
     */
    protected ?string $description = null;

    /**
     * @var string|null $version An optional version of the payment gateway.
     */
    protected ?string $version = null;

    /**
     * @var array<key-of<GatewayAction>, GatewayActionInterface|class-string<GatewayActionInterface>> $actions
     * The list of supported actions for this gateway.
     * This should be defined in the concrete implementation of the gateway.
     */
    protected array $actions = [];

    /**
     * @var string $productionUrl The URL for the production environment of the payment gateway.
     * This should be defined in the concrete implementation of the gateway if production mode is supported.
     */
    protected string $productionUrl;

    /**
     * @var string $sandboxUrl The URL for the sandbox environment of the payment gateway.
     * This should be defined in the concrete implementation of the gateway if sandbox mode is supported.
     */
    protected string $sandboxUrl;

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        if (isset($this->name)) {
            return $this->name;
        }
        $className = get_class($this);
        // take last
        $name = substr($className, strrpos($className, '\\') + 1);
        // eg: "PayPalExpressGateway" => "PayPal Express Gateway"
        // eg: PayPal_Express_Gateway => "PayPal Express Gateway"
        $name = str_replace('_', ' ', $name);
        $spacedName = preg_replace_callback(
            '/([a-z])([A-Z])/',
            function ($matches) {
                return $matches[1] . ' ' . $matches[2];
            },
            $name
        );
        return $this->name = trim(preg_replace('/\s+/', ' ', $spacedName));
    }

    /**
     * @inheritdoc
     */
    public function getSupportedActions(): array
    {
        $actions = [];
        foreach ($this->actions as $action => $_handler) {
            $action = GatewayAction::tryFrom(strtoupper($action));
            if ($action) {
                $actions[] = $action;
            }
        }
        return $actions;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @inheritdoc
     */
    public function isSupportSandbox(): bool
    {
        try {
            $url = $this->getSandboxUrl();
            return !empty($url);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function isSupportProduction(): bool
    {
        try {
            $url = $this->getProductionUrl();
            return !empty($url);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function getSandboxUrl(): string
    {
        if (!isset($this->sandboxUrl)) {
            throw new UnsupportedModeException(
                gateway: $this,
                mode: 'sandbox'
            );
        }
        return $this->sandboxUrl;
    }

    /**
     * @inheritdoc
     */
    public function getProductionUrl(): string
    {
        if (!isset($this->productionUrl)) {
            throw new UnsupportedModeException(
                gateway: $this,
                mode: 'production'
            );
        }
        return $this->productionUrl;
    }

    /**
     * @inheritdoc
     */
    public function hasAction(GatewayAction $action): bool
    {
        return isset($this->actions[$action->value]);
    }

    /**
     * @inheritdoc
     * @template T of GatewayActionInterface
     * @return T
     *
     * @note
     * Override tbhis method if you want to pass parameters to the action handlers.
     * The default implementation ignores the $parameters argument,
     * but you can use it to customize the action handler retrieval logic based on the provided parameters.
     * For example,
     * you could use the parameters to determine which specific action handler to return for a given action,
     * @abstract
     */
    public function getAction(GatewayAction $action, array $parameters = []): GatewayActionInterface
    {
        if (!$this->hasAction($action)) {
            throw new UnsupportedActionException(
                gateway: $this,
                action: $action
            );
        }
        if (!is_object($this->actions[$action->value])) {
            /**
             * @var class-string<GatewayActionInterface> $className
             */
            $className = $this->actions[$action->value];
            $this->actions[$action->value] = new $className($this);
        }
        /** @var T $action */
        $action = $this->actions[$action->value];
        return $action;
    }

    /**
     * Post-processes the transaction after the HTTP request has been made.
     *
     * @param TransactionProcessorInterface $processor
     * @param LoggerInterface|null $logger
     * @return TransactionProcessor
     * @abstract
     * @noinspection PhpUnusedParameterInspection
     */
    protected function postRequest(
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): TransactionProcessor {
        // You can implement any post-processing logic here,
        // such as logging, updating transaction status, etc.
        return $processor;
    }

    /**
     * Prepares the HTTP request for processing the transaction.
     * For example adding authentication, manipulating headers, etc.
     *
     * @param RequestInterface $request
     * @param GatewayActionInterface $actionHandler
     * @param TransactionInterface $transaction
     * @param LoggerInterface|null $logger
     * @return RequestInterface
     * @abstract
     * @noinspection PhpUnusedParameterInspection
     */
    protected function prepareRequest(
        RequestInterface $request,
        GatewayActionInterface $actionHandler,
        TransactionInterface $transaction,
        ?LoggerInterface $logger = null
    ): RequestInterface {
        return $request;
    }

    /**
     * @inheritdoc
     * @final
     */
    public function process(
        TransactionInterface $transaction,
        ClientInterface $client,
        ?LoggerInterface $logger = null
    ): TransactionProcessorInterface {
        if (!$this->hasAction($transaction->getAction())) {
            throw new UnsupportedActionException(
                $this,
                $transaction->getAction()
            );
        }
        $action = $this->getAction($transaction->getAction(), $transaction->getParameters());
        if (!$action->isProcessable($transaction)) {
            throw new UnsupportedActionException(
                gateway: $this,
                action: $transaction->getAction(),
                message: sprintf(
                    'The action "%s" is not processable for the given transaction for Gateway "%s".',
                    $transaction->getAction()->value,
                    $this->getName()
                )
            );
        }
        $request = $action->createRequest($transaction, $logger);
        $processor = new TransactionProcessor(
            gateway: $this,
            transaction: $transaction,
            request: $this->prepareRequest($request, $action, $transaction, $logger)
        );
        try {
            $processor = $processor->process($client, $logger);
        } finally {
            return $this->postRequest($processor);
        }
    }
}
