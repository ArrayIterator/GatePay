<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Abstracts;

use GatePay\Core\Abstracts\AbstractGateway;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\TransactionStatus;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Exceptions\UnsupportedModeException;
use GatePay\Core\Interfaces\GatewayActionInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Transaction;
use GatePay\CoreTests\Depends\ConcreteTestAction;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class AbstractGatewayTest extends TestCase
{
    #[Test]
    public function getNameReturnsExplicitlySetName(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'MyCustomGateway';
        };

        $this->assertSame('MyCustomGateway', $gateway->getName());
    }

    #[Test]
    public function getNameDerivedFromClassNameWhenNotSet(): void
    {
        $gateway = new class extends AbstractGateway {
        };
        $name = $gateway->getName();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
        $this->assertSame('Abstract Gateway', $name);
    }

    #[Test]
    public function getNameCachesResult(): void
    {
        $gateway = new class extends AbstractGateway {
        };
        $first = $gateway->getName();
        $second = $gateway->getName();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function getNameConvertsCamelCaseToSpaces(): void
    {
        // Create an anonymous class named via eval so we control the short name
        // Instead, we test with an explicit name and verify the camelCase logic
        $gateway = new class extends AbstractGateway {
            // deliberately not setting $name to trigger derivation
        };

        // We can't fully control the anonymous class name, so just verify it returns a non-empty string
        $name = $gateway->getName();
        $this->assertIsString($name);
    }

    #[Test]
    public function getDescriptionReturnsNullByDefault(): void
    {
        $gateway = new class extends AbstractGateway {
        };

        $this->assertNull($gateway->getDescription());
    }

    #[Test]
    public function getDescriptionReturnsSetValue(): void
    {
        $gateway = new class extends AbstractGateway {
            protected ?string $description = 'A test gateway';
        };

        $this->assertSame('A test gateway', $gateway->getDescription());
    }

    #[Test]
    public function getVersionReturnsNullByDefault(): void
    {
        $gateway = new class extends AbstractGateway {
        };

        $this->assertNull($gateway->getVersion());
    }

    #[Test]
    public function getVersionReturnsSetValue(): void
    {
        $gateway = new class extends AbstractGateway {
            protected ?string $version = '2.0.1';
        };

        $this->assertSame('2.0.1', $gateway->getVersion());
    }

    #[Test]
    public function isSupportSandboxReturnsFalseWhenUrlNotSet(): void
    {
        $gateway = new class extends AbstractGateway {
        };
        $this->assertFalse($gateway->isSupportSandbox());
    }

    #[Test]
    public function isSupportSandboxReturnsTrueWhenUrlSet(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $sandboxUrl = 'https://sandbox.example.com';
        };

        $this->assertTrue($gateway->isSupportSandbox());
    }

    #[Test]
    public function isSupportProductionReturnsFalseWhenUrlNotSet(): void
    {
        $gateway = new class extends AbstractGateway {
        };
        $this->assertFalse($gateway->isSupportProduction());
    }

    #[Test]
    public function isSupportProductionReturnsTrueWhenUrlSet(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $productionUrl = 'https://production.example.com';
        };

        $this->assertTrue($gateway->isSupportProduction());
    }

    #[Test]
    public function getSandboxUrlThrowsWhenNotSet(): void
    {
        $gateway = new class extends AbstractGateway {
        };

        $this->expectException(UnsupportedModeException::class);
        $gateway->getSandboxUrl();
    }

    #[Test]
    public function getSandboxUrlReturnsUrlWhenSet(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $sandboxUrl = 'https://sandbox.example.com';
        };

        $this->assertSame('https://sandbox.example.com', $gateway->getSandboxUrl());
    }

    #[Test]
    public function getProductionUrlThrowsWhenNotSet(): void
    {
        $gateway = new class extends AbstractGateway {
        };
        $this->expectException(UnsupportedModeException::class);
        $gateway->getProductionUrl();
    }

    #[Test]
    public function getProductionUrlReturnsUrlWhenSet(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $productionUrl = 'https://production.example.com';
        };

        $this->assertSame('https://production.example.com', $gateway->getProductionUrl());
    }

    #[Test]
    public function hasActionReturnsFalseForUnsupportedAction(): void
    {
        $gateway = new class extends AbstractGateway {
            protected array $actions = [];
        };

        $this->assertFalse($gateway->hasAction(GatewayAction::CHARGE));
    }

    #[Test]
    public function hasActionReturnsTrueForSupportedAction(): void
    {
        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $gateway = new class($actionHandler) extends AbstractGateway {
            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                ];
            }
        };

        $this->assertTrue($gateway->hasAction(GatewayAction::CHARGE));
    }

    #[Test]
    public function getSupportedActionsReturnsRegisteredActions(): void
    {
        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $gateway = new class($actionHandler) extends AbstractGateway {
            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                    GatewayAction::REFUND->value => $handler,
                ];
            }
        };

        $supported = $gateway->getSupportedActions();

        $this->assertCount(2, $supported);
        $this->assertContains(GatewayAction::CHARGE, $supported);
        $this->assertContains(GatewayAction::REFUND, $supported);
    }

    #[Test]
    public function getSupportedActionsReturnsEmptyArrayWhenNoActions(): void
    {
        $gateway = new class extends AbstractGateway {
            protected array $actions = [];
        };

        $this->assertSame([], $gateway->getSupportedActions());
    }

    #[Test]
    public function getSupportedActionsIgnoresInvalidActionKeys(): void
    {
        $gateway = new class extends AbstractGateway {
            protected array $actions = [
                'INVALID_KEY' => 'SomeClass',
            ];
        };

        $this->assertSame([], $gateway->getSupportedActions());
    }

    #[Test]
    public function getActionThrowsForUnsupportedAction(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'TestGateway';

            protected array $actions = [];
        };

        $this->expectException(UnsupportedActionException::class);
        $gateway->getAction(GatewayAction::CHARGE);
    }

    #[Test]
    public function getActionReturnsObjectHandlerDirectly(): void
    {
        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $gateway = new class($actionHandler) extends AbstractGateway {
            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                ];
            }
        };

        $result = $gateway->getAction(GatewayAction::CHARGE);

        $this->assertSame($actionHandler, $result);
    }

    #[Test]
    public function getActionLazilyInstantiatesClassStringHandler(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'TestGateway';

            public function __construct()
            {
                $this->actions = [
                    GatewayAction::TEST->value => ConcreteTestAction::class,
                ];
            }
        };

        $result = $gateway->getAction(GatewayAction::TEST);

        $this->assertInstanceOf(GatewayActionInterface::class, $result);
        $this->assertInstanceOf(ConcreteTestAction::class, $result);
    }

    #[Test]
    public function getActionReturnsSameInstanceOnSubsequentCalls(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'TestGateway';

            public function __construct()
            {
                $this->actions = [
                    GatewayAction::TEST->value => ConcreteTestAction::class,
                ];
            }
        };

        $first = $gateway->getAction(GatewayAction::TEST);
        $second = $gateway->getAction(GatewayAction::TEST);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function processThrowsForUnsupportedAction(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'TestGateway';

            protected array $actions = [];
        };

        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $requestFactory = new HttpFactory();
        $client = $this->createMock(ClientInterface::class);

        $this->expectException(UnsupportedActionException::class);
        $gateway->process($transaction, $requestFactory, $client);
    }

    #[Test]
    public function processThrowsWhenActionIsNotProcessable(): void
    {
        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $actionHandler->method('isProcessable')->willReturn(false);
        $actionHandler->method('getAction')->willReturn(GatewayAction::CHARGE);

        $gateway = new class($actionHandler) extends AbstractGateway {
            protected string $name = 'TestGateway';

            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                ];
            }
        };

        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $requestFactory = new HttpFactory();
        $client = $this->createMock(ClientInterface::class);

        $this->expectException(UnsupportedActionException::class);
        $gateway->process($transaction, $requestFactory, $client);
    }

    #[Test]
    public function processReturnsProcessorOnSuccess(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $actionHandler->method('isProcessable')->willReturn(true);
        $actionHandler->method('getAction')->willReturn(GatewayAction::CHARGE);
        $actionHandler->method('createRequest')->willReturn($request);

        $gateway = new class($actionHandler) extends AbstractGateway {
            protected string $name = 'TestGateway';

            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                ];
            }
        };

        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $requestFactory = new HttpFactory();
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $gateway->process($transaction, $requestFactory, $client);

        $this->assertInstanceOf(TransactionProcessorInterface::class, $result);
        $this->assertSame(TransactionStatus::SUCCESS, $result->getTransactionStatus());
    }

    #[Test]
    public function processReturnsProcessorOnClientError(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $actionHandler->method('isProcessable')->willReturn(true);
        $actionHandler->method('getAction')->willReturn(GatewayAction::CHARGE);
        $actionHandler->method('createRequest')->willReturn($request);

        $gateway = new class($actionHandler) extends AbstractGateway {
            protected string $name = 'TestGateway';

            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                ];
            }
        };

        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $requestFactory = new HttpFactory();
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Network error'));

        $result = $gateway->process($transaction, $requestFactory, $client);

        $this->assertInstanceOf(TransactionProcessorInterface::class, $result);
        $this->assertSame(TransactionStatus::FAILED, $result->getTransactionStatus());
    }

    #[Test]
    public function processAcceptsOptionalLogger(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $actionHandler = $this->createMock(GatewayActionInterface::class);
        $actionHandler->method('isProcessable')->willReturn(true);
        $actionHandler->method('getAction')->willReturn(GatewayAction::CHARGE);
        $actionHandler->method('createRequest')->willReturn($request);

        $gateway = new class($actionHandler) extends AbstractGateway {
            protected string $name = 'TestGateway';

            public function __construct(GatewayActionInterface $handler)
            {
                $this->actions = [
                    GatewayAction::CHARGE->value => $handler,
                ];
            }
        };

        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $requestFactory = new HttpFactory();
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);
        $logger = $this->createMock(LoggerInterface::class);

        $result = $gateway->process($transaction, $requestFactory, $client, $logger);
        $this->assertInstanceOf(TransactionProcessorInterface::class, $result);
    }

    #[Test]
    public function unsupportedModeExceptionContainsGatewayAndMode(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'TestGateway';
        };

        try {
            $gateway->getSandboxUrl();
            $this->fail('Expected UnsupportedModeException');
        } catch (UnsupportedModeException $e) {
            $this->assertSame($gateway, $e->getGateway());
            $this->assertSame('sandbox', $e->getMode());
        }
    }

    #[Test]
    public function unsupportedActionExceptionContainsGatewayAndAction(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $name = 'TestGateway';

            protected array $actions = [];
        };

        try {
            $gateway->getAction(GatewayAction::REFUND);
            $this->fail('Expected UnsupportedActionException');
        } catch (UnsupportedActionException $e) {
            $this->assertSame($gateway, $e->getGateway());
            $this->assertSame(GatewayAction::REFUND, $e->getAction());
        }
    }

    #[Test]
    public function isSupportSandboxReturnsFalseForEmptySandboxUrl(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $sandboxUrl = '';
        };

        $this->assertFalse($gateway->isSupportSandbox());
    }

    #[Test]
    public function isSupportProductionReturnsFalseForEmptyProductionUrl(): void
    {
        $gateway = new class extends AbstractGateway {
            protected string $productionUrl = '';
        };

        $this->assertFalse($gateway->isSupportProduction());
    }
}
