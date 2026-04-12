<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Abstracts;

use GatePay\Core\Abstracts\AbstractGatewayAction;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class AbstractGatewayActionTest extends TestCase
{
    #[Test]
    public function constructorSetsGateway(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);

        $this->assertSame($gateway, $action->getGateway());
    }

    #[Test]
    public function gatewayPropertyIsReadonly(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);

        $reflection = new \ReflectionProperty($action, 'gateway');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isProcessableReturnsTrueWhenActionMatches(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::CHARGE);

        $this->assertTrue($action->isProcessable($transaction));
    }

    #[Test]
    public function isProcessableReturnsFalseWhenActionDoesNotMatch(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::REFUND);

        $this->assertFalse($action->isProcessable($transaction));
    }

    #[Test]
    public function assertProcessablePassesWhenGatewayAndActionMatch(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::CHARGE);

        $reflection = new \ReflectionMethod($action, 'assertProcessable');

        $this->expectNotToPerformAssertions();
        $reflection->invoke($action, $gateway, $transaction);
    }

    #[Test]
    public function assertProcessableThrowsWhenGatewayMismatch(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $otherGateway = $this->createMock(GatewayInterface::class);
        $otherGateway->method('getName')->willReturn('OtherGateway');
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::CHARGE);

        $reflection = new \ReflectionMethod($action, 'assertProcessable');

        $this->expectException(UnsupportedActionException::class);
        $this->expectExceptionMessageMatches('/mismatched/');
        $reflection->invoke($action, $otherGateway, $transaction);
    }

    #[Test]
    public function assertProcessableThrowsWhenActionMismatch(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getName')->willReturn('TestGateway');
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::REFUND);

        $reflection = new \ReflectionMethod($action, 'assertProcessable');

        $this->expectException(UnsupportedActionException::class);
        $this->expectExceptionMessageMatches('/does not match/');
        $reflection->invoke($action, $gateway, $transaction);
    }

    #[Test]
    public function assertProcessableThrowsWhenNotProcessable(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getName')->willReturn('TestGateway');
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE, false);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::CHARGE);

        $reflection = new \ReflectionMethod($action, 'assertProcessable');

        $this->expectException(UnsupportedActionException::class);
        $this->expectExceptionMessageMatches('/not processable/');
        $reflection->invoke($action, $gateway, $transaction);
    }

    #[Test]
    public function assertProcessableExceptionHasWarningCode(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $otherGateway = $this->createMock(GatewayInterface::class);
        $otherGateway->method('getName')->willReturn('OtherGateway');
        $action = $this->createConcreteAction($gateway, GatewayAction::CHARGE);
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getAction')->willReturn(GatewayAction::CHARGE);

        $reflection = new \ReflectionMethod($action, 'assertProcessable');

        try {
            $reflection->invoke($action, $otherGateway, $transaction);
            $this->fail('Expected UnsupportedActionException');
        } catch (UnsupportedActionException $e) {
            $this->assertSame(E_WARNING, $e->getCode());
        }
    }

    #[Test]
    public function getActionReturnsConfiguredAction(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $action = $this->createConcreteAction($gateway, GatewayAction::REFUND);

        $this->assertSame(GatewayAction::REFUND, $action->getAction());
    }

    private function createConcreteAction(
        GatewayInterface $gateway,
        GatewayAction $gatewayAction,
        bool $processable = true
    ): AbstractGatewayAction {
        return new class($gateway, $gatewayAction, $processable) extends AbstractGatewayAction {
            private readonly GatewayAction $gatewayAction;
            public function __construct(
                GatewayInterface                $gateway,
                ?GatewayAction $gatewayAction = null,
                private readonly bool           $processable = true
            ) {
                parent::__construct($gateway);
                $this->gatewayAction = $gatewayAction;
            }

            public function getAction(): GatewayAction
            {
                return $this->gatewayAction;
            }

            public function isProcessable(TransactionInterface $transaction): bool
            {
                if (!$this->processable) {
                    return false;
                }
                return parent::isProcessable($transaction);
            }

            public function createRequest(
                GatewayInterface                               $gateway,
                TransactionInterface                           $transaction,
                RequestFactoryInterface&StreamFactoryInterface $factory,
                ?LoggerInterface                               $logger = null
            ): RequestInterface {
                $this->assertProcessable($gateway, $transaction);
                return $factory->createRequest('GET', 'https://test.example.com');
            }
        };
    }
}
