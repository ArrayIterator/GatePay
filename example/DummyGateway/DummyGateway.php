<?php
declare(strict_types=1);

namespace GatePay\Example\DummyGateway;

use GatePay\Core\Abstracts\AbstractGateway;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Example\DummyGateway\Actions\TestAction;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;

class DummyGateway extends AbstractGateway
{
    protected string $name = "DummyGateway";

    protected ClientInterface $client;

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
        ?LoggerInterface $logger = null
    ): ClientInterface {
        return $this->client;
    }
}
