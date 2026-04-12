<?php
declare(strict_types=1);

namespace GatePay\Core\Abstracts;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Exceptions\UnsupportedModeException;
use GatePay\Core\Interfaces\GatewayActionInterface;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\TransactionProcessor;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function get_class;
use function is_object;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function str_contains;
use function str_replace;
use function strrpos;
use function strtoupper;
use function substr;
use function trim;

/**
 * AbstractGateway provides a base implementation of the GatewayInterface.
 * It includes common functionality for handling payment actions and processing transactions.
 * Concrete payment gateways can extend this class and implement the specific details for each action.
 *
 * @template Action of GatewayAction
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
     * @var array<value-of<Action>, GatewayActionInterface<Action>|class-string<GatewayActionInterface<Action>>>
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
        // check if anonymous class
        if (str_contains($className, '@anonymous')) {
            $explode = explode('@anonymous', $className, 2);
            $className = $explode[0];
        }
        $name = $className;
        if (str_contains($className, "\\")) {
            // take last
            $name = substr($className, strrpos($className, '\\') + 1);
        }
        // eg: "PayPalExpressGateway" => "PayPal Express Gateway"
        // eg: PayPal_Express_Gateway => "PayPal Express Gateway"
        $name = str_replace('_', ' ', $name);
        $spacedName = (string)preg_replace_callback(
            '/([a-z])([A-Z])/',
            function ($matches) {
                return $matches[1] . ' ' . $matches[2];
            },
            $name
        );
        return $this->name = trim((string)preg_replace('/\s+/', ' ', $spacedName));
    }

    /**
     * @inheritdoc
     */
    public function getSupportedActions(): array
    {
        $actions = [];
        foreach ($this->actions as $action => $_handler) {
            $action = GatewayAction::tryFrom(strtoupper($action));
            /**
             * @var GatewayAction|null $action
             */
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
     * @param GatewayAction $action
     * @return GatewayActionInterface<GatewayAction>
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
            $className = $this->actions[$action->value];
            /**
             * @var class-string<GatewayActionInterface<GatewayAction>> $className
             */
            $object = new $className($this);
            /**
             * @var GatewayActionInterface<Action> $object
             */
            $this->actions[$action->value] = $object;
        }
        /**
         * @var GatewayActionInterface<GatewayAction> $action
         */
        $action = $this->actions[$action->value];
        return $action;
    }

    /**
     * Prepares the HTTP client for processing the transaction.
     * For example adding authentication, manipulating headers, etc.
     * Or wrapping the client with a decorator that adds additional functionality
     * (e.g., logging, retry logic, etc.).
     *
     * @param ClientInterface $client
     * @param TransactionProcessorInterface $processor
     * @param LoggerInterface|null $logger
     * @return ClientInterface
     * @throws UnsupportedActionException
     *      if the transaction processor is not associated with this gateway,
     *      or if the action of the transaction is not supported by this gateway.
     * @abstract
     * @noinspection PhpUnusedParameterInspection
     */
    protected function prepareClient(
        ClientInterface               $client,
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ): ClientInterface {
        return $client;
    }

    /**
     * Post-processes the transaction after the HTTP request has been made.
     *
     * @param TransactionProcessorInterface $processor
     * @param LoggerInterface|null $logger
     * @return TransactionProcessorInterface
     * @abstract
     * @noinspection PhpUnusedParameterInspection
     */
    protected function postRequest(
        TransactionProcessorInterface $processor,
        ?LoggerInterface              $logger = null
    ): TransactionProcessorInterface {
        // You can implement any post-processing logic here,
        // such as logging, updating transaction status, etc.
        return $processor;
    }

    /**
     * Prepares the HTTP request for processing the transaction.
     * For example adding authentication, manipulating headers, etc.
     *
     * @param RequestInterface $request
     * @param GatewayActionInterface<GatewayAction> $actionHandler
     * @param TransactionInterface $transaction
     * @param LoggerInterface|null $logger
     * @return RequestInterface
     * @abstract
     * @noinspection PhpUnusedParameterInspection
     */
    protected function prepareRequest(
        RequestInterface       $request,
        GatewayActionInterface $actionHandler,
        TransactionInterface   $transaction,
        ?LoggerInterface       $logger = null
    ): RequestInterface {
        return $request;
    }

    /**
     * @inheritdoc
     * @final
     */
    public function process(
        TransactionInterface                           $transaction,
        RequestFactoryInterface&StreamFactoryInterface $factory,
        ClientInterface                                $client,
        ?LoggerInterface                               $logger = null
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
        $request = $action->createRequest($this, $transaction, $factory, $logger);
        $processor = new TransactionProcessor(
            gateway: $this,
            transaction: $transaction,
            request: $this->prepareRequest($request, $action, $transaction, $logger)
        );
        try {
            $client = $this->prepareClient($client, $processor, $logger);
            $processor = $processor->process($client, $logger);
        } finally {
            return $this->postRequest($processor);
        }
    }
}
