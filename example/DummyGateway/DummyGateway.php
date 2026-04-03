<?php
declare(strict_types=1);

namespace GatePay\Example\DummyGateway;

use GatePay\Core\Abstracts\AbstractGateway;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Example\DummyGateway\Actions\TestAction;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;

class DummyGateway extends AbstractGateway
{
    /**
     * @var string
     */
    protected string $name = "DummyGateway";

    /**
     * @var LocalClient
     */
    protected LocalClient $client;

    /**
     * @param ResponseFactoryInterface $responseFactory
     * The response factory to create HTTP responses.
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
    ) {
        $this->client = new LocalClient($responseFactory);
        $this->actions = [
            // use class name for lazy initialization of actions
            GatewayAction::CUSTOM->value => TestAction::class
        ];
    }

    /**
     * @inheritdoc
     */
    protected function prepareClient(
        ClientInterface $client,
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): ClientInterface {
        if ($processor->getGateway() !== $this) {
            throw new UnsupportedActionException(
                $this,
                $processor->getTransaction()->getAction(),
                'The transaction processor is not associated with this gateway.',
            );
        }
        // return the local client instead of the provided client,
        // since this gateway uses a local client for processing transactions.
        return $this->client;
    }
}
