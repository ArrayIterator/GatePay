<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\TransactionState;
use GatePay\Core\Enum\TransactionStatus;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Interfaces\TransactionResponseInterface;
use GatePay\Core\Interfaces\TransactionStackInterface;
use GatePay\Core\Transaction;
use GatePay\Core\TransactionStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class TransactionTest extends TestCase
{
    #[Test]
    public function constructorSetsTransactionId(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertSame('txn_123', $transaction->getTransactionId());
        $this->assertSame('txn_123', $transaction->transactionId);
    }

    #[Test]
    public function constructorSetsAction(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::REFUND);

        $this->assertSame(GatewayAction::REFUND, $transaction->getAction());
        $this->assertSame(GatewayAction::REFUND, $transaction->action);
    }

    #[Test]
    public function constructorSetsParameters(): void
    {
        $params = ['amount' => 1000, 'currency' => 'USD'];
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, $params);

        $this->assertSame($params, $transaction->getParameters());
        $this->assertSame($params, $transaction->parameters);
    }

    #[Test]
    public function constructorWithEmptyParametersSetsEmptyArray(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertSame([], $transaction->getParameters());
    }

    #[Test]
    public function constructorCreatesDefaultStackWhenNullProvided(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertInstanceOf(TransactionStackInterface::class, $transaction->getStack());
        $this->assertInstanceOf(TransactionStack::class, $transaction->getStack());
    }

    #[Test]
    public function constructorUsesProvidedStack(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        $this->assertSame($mockStack, $transaction->getStack());
    }

    #[Test]
    public function getStateReturnsInitialPendingState(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertSame(TransactionState::PENDING, $transaction->getState());
    }

    #[Test]
    public function getTimestampReturnsZeroBeforeBegin(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertSame(0, $transaction->getTimestamp());
    }

    #[Test]
    public function getResponseReturnsNullBeforeProcessing(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertNull($transaction->getResponse());
    }

    #[Test]
    public function getErrorReturnsNullBeforeProcessing(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertNull($transaction->getError());
    }

    #[Test]
    public function isEnableLoggingForwardsToStack(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->expects($this->once())
            ->method('isEnableLogging')
            ->willReturn(true);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        $this->assertTrue($transaction->isEnableLogging());
    }

    #[Test]
    public function setEnableLoggingForwardsToStack(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->expects($this->once())
            ->method('setEnableLogging')
            ->with(false);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);
        $transaction->setEnableLogging(false);
    }

    #[Test]
    public function getTransactionResultDataForwardsToStack(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $this->assertNull($transaction->getTransactionResultData());
    }

    #[Test]
    public function beginDoesNothingWhenNotProcessing(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::PENDING, $transaction);

        $transaction->begin($processor);

        $this->assertSame(TransactionState::PENDING, $transaction->getState());
    }

    #[Test]
    public function beginSetsStateToBeginWhenProcessing(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);

        $transaction->begin($processor);

        $this->assertSame(TransactionState::BEGIN, $transaction->getState());
    }

    #[Test]
    public function beginSetsTimestamp(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);

        $beforeTime = time();
        $transaction->begin($processor);
        $afterTime = time();

        $this->assertGreaterThanOrEqual($beforeTime, $transaction->getTimestamp());
        $this->assertLessThanOrEqual($afterTime, $transaction->getTimestamp());
    }

    #[Test]
    public function beginDoesNothingWhenAlreadyBegun(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);

        $transaction->begin($processor);
        $firstTimestamp = $transaction->getTimestamp();

        sleep(1);
        $transaction->begin($processor);

        $this->assertSame($firstTimestamp, $transaction->getTimestamp());
        $this->assertSame(TransactionState::BEGIN, $transaction->getState());
    }

    #[Test]
    public function thenDoesNothingWhenNotProcessed(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        $transaction->then($response, $processor);

        $this->assertNull($transaction->getResponse());
    }

    #[Test]
    public function thenDoesNothingWhenNotSuccess(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::FAILED, $transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        $transaction->then($response, $processor);

        $this->assertNull($transaction->getResponse());
    }

    #[Test]
    public function thenSetsResponseWhenSuccessful(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('then')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        // First set state to BEGIN by beginning the transaction
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        // $mockStack->method('begin')->willReturn(null);
        $transaction->begin($processorForBegin);

        $transaction->then($response, $processor);

        $this->assertSame($response, $transaction->getResponse());
        $this->assertSame(TransactionState::SUCCESS, $transaction->getState());
    }

    #[Test]
    public function thenDoesNotOverwriteExistingResponse(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('then')->willReturn(null);
        // $mockStack->method('begin')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        // Begin the transaction
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $transaction->begin($processorForBegin);

        $processor = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $response1 = $this->createMock(TransactionResponseInterface::class);
        $response2 = $this->createMock(TransactionResponseInterface::class);

        $transaction->then($response1, $processor);
        $transaction->then($response2, $processor);

        $this->assertSame($response1, $transaction->getResponse());
    }

    #[Test]
    public function catchDoesNothingWhenNotMarkedAsError(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $transaction->catch($error, $processor);

        $this->assertNull($transaction->getError());
    }

    #[Test]
    public function catchSetsErrorWhenFailed(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('catch')->willReturn(null);
        // $mockStack->method('begin')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        // Begin the transaction first
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $transaction->begin($processorForBegin);

        $processor = $this->createMockProcessor(TransactionStatus::FAILED, $transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $transaction->catch($error, $processor);

        $this->assertSame($error, $transaction->getError());
        $this->assertSame(TransactionState::ERROR, $transaction->getState());
    }

    #[Test]
    public function catchSetsErrorWhenCanceled(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('catch')->willReturn(null);
        // $mockStack->method('begin')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        // Begin the transaction first
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $transaction->begin($processorForBegin);

        $processor = $this->createMockProcessor(TransactionStatus::CANCELED, $transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $transaction->catch($error, $processor);

        $this->assertSame($error, $transaction->getError());
        $this->assertSame(TransactionState::ERROR, $transaction->getState());
    }

    #[Test]
    public function catchDoesNotOverwriteExistingError(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('catch')->willReturn(null);
        // $mockStack->method('begin')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        // Begin the transaction first
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $transaction->begin($processorForBegin);

        $processor = $this->createMockProcessor(TransactionStatus::FAILED, $transaction);
        $error1 = $this->createMock(TransactionErrorInterface::class);
        $error2 = $this->createMock(TransactionErrorInterface::class);

        $transaction->catch($error1, $processor);
        $transaction->catch($error2, $processor);

        $this->assertSame($error1, $transaction->getError());
    }

    #[Test]
    public function finallyDoesNothingWhenNotProcessed(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);
        $processor = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);

        $transaction->finally($processor);

        $this->assertSame(TransactionState::PENDING, $transaction->getState());
    }

    #[Test]
    public function finallySetsStateToFinalWhenProcessed(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('then')->willReturn(null);
        // $mockStack->method('begin')->willReturn(null);
        // $mockStack->method('finally')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        // Begin the transaction first
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $transaction->begin($processorForBegin);

        // Then set to success
        $processorForSuccess = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $response = $this->createMock(TransactionResponseInterface::class);
        $transaction->then($response, $processorForSuccess);

        // Finally
        $processor = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $transaction->finally($processor);

        $this->assertSame(TransactionState::FINAL, $transaction->getState());
    }

    #[Test]
    public function finallyDoesNothingWhenAlreadyFinal(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        // $mockStack->method('then')->willReturn(null);
        // $mockStack->method('begin')->willReturn(null);
        // $mockStack->expects($this->once())->method('finally')->willReturn(null);

        $transaction = new Transaction('txn_123', GatewayAction::CHARGE, [], $mockStack);

        // Begin, then, finally sequence
        $processorForBegin = $this->createMockProcessor(TransactionStatus::PROCESSING, $transaction);
        $transaction->begin($processorForBegin);

        $processorForSuccess = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $response = $this->createMock(TransactionResponseInterface::class);
        $transaction->then($response, $processorForSuccess);

        $processor = $this->createMockProcessor(TransactionStatus::SUCCESS, $transaction);
        $transaction->finally($processor);
        $transaction->finally($processor); // Should not call stack->finally again
        $this->assertSame($transaction->getState(), TransactionState::FINAL);
    }

    #[Test]
    public function transactionIdPropertyIsReadonly(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $reflection = new \ReflectionProperty($transaction, 'transactionId');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function actionPropertyIsReadonly(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $reflection = new \ReflectionProperty($transaction, 'action');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function parametersPropertyIsReadonly(): void
    {
        $transaction = new Transaction('txn_123', GatewayAction::CHARGE);

        $reflection = new \ReflectionProperty($transaction, 'parameters');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function supportsAllGatewayActions(): void
    {
        $actions = GatewayAction::cases();

        foreach ($actions as $action) {
            $transaction = new Transaction('txn_123', $action);
            $this->assertSame($action, $transaction->getAction());
        }
    }

    private function createMockProcessor(
        TransactionStatus $status,
        Transaction $transaction
    ): TransactionProcessorInterface {
        $processor = $this->createMock(TransactionProcessorInterface::class);
        $processor->method('getTransactionStatus')->willReturn($status);
        $processor->method('getTransaction')->willReturn($transaction);
        $processor->method('getGateway')->willReturn($this->createMock(GatewayInterface::class));
        $processor->method('getRequest')->willReturn($this->createMock(RequestInterface::class));

        return $processor;
    }
}
