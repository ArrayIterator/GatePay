<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\TransactionStatus;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Interfaces\TransactionStackInterface;
use GatePay\Core\Transaction;
use GatePay\Core\TransactionProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

class TransactionProcessorTest extends TestCase
{
    #[Test]
    public function constructorSetsGateway(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $request = $this->createMock(RequestInterface::class);

        $processor = new TransactionProcessor($gateway, $transaction, $request);

        $this->assertSame($gateway, $processor->getGateway());
    }

    #[Test]
    public function constructorSetsTransaction(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $request = $this->createMock(RequestInterface::class);

        $processor = new TransactionProcessor($gateway, $transaction, $request);

        $this->assertSame($transaction, $processor->getTransaction());
    }

    #[Test]
    public function constructorSetsRequest(): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $request = $this->createMock(RequestInterface::class);

        $processor = new TransactionProcessor($gateway, $transaction, $request);

        $this->assertSame($request, $processor->getRequest());
    }

    #[Test]
    public function getTransactionResponseReturnsNullBeforeProcessing(): void
    {
        $processor = $this->createProcessor();

        $this->assertNull($processor->getTransactionResponse());
    }

    #[Test]
    public function getTransactionErrorReturnsNullBeforeProcessing(): void
    {
        $processor = $this->createProcessor();

        $this->assertNull($processor->getTransactionError());
    }

    #[Test]
    public function inReturnsTrueForSameTransaction(): void
    {
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $processor = $this->createProcessor($transaction);

        $this->assertTrue($processor->in($transaction));
    }

    #[Test]
    public function inReturnsFalseForDifferentTransaction(): void
    {
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE);
        $otherTransaction = new Transaction('txn_2', GatewayAction::REFUND);
        $processor = $this->createProcessor($transaction);

        $this->assertFalse($processor->in($otherTransaction));
    }

    #[Test]
    public function processSuccessfulRequestSetsStatusToSuccess(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertSame(TransactionStatus::SUCCESS, $result->getTransactionStatus());
    }

    #[Test]
    public function processSuccessfulRequestSetsTransactionResponse(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertNotNull($result->getTransactionResponse());
        $this->assertSame($response, $result->getTransactionResponse()->getResponse());
    }

    #[Test]
    public function processFailedRequestSetsStatusToFailed(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Network error'));

        $result = $processor->process($client);

        $this->assertSame(TransactionStatus::FAILED, $result->getTransactionStatus());
    }

    #[Test]
    public function processFailedRequestSetsTransactionError(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Network error'));

        $result = $processor->process($client);

        $this->assertNotNull($result->getTransactionError());
        $this->assertSame('Network error', $result->getTransactionError()->getException()->getMessage());
    }

    #[Test]
    public function processReturnsSelfWhenStatusIsPending(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $gateway = $this->createMock(GatewayInterface::class);
        $request = $this->createMock(RequestInterface::class);

        $processor = new TransactionProcessor($gateway, $transaction, $request);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $firstResult = $processor->process($client);

        $this->assertSame(TransactionStatus::SUCCESS, $firstResult->getTransactionStatus());
    }

    #[Test]
    public function processCallsTransactionBegin(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->expects($this->once())->method('begin');
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $processor->process($client);
    }

    #[Test]
    public function processCallsTransactionThenOnSuccess(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->expects($this->once())->method('then');
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $processor->process($client);
    }

    #[Test]
    public function processCallsTransactionCatchOnFailure(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->expects($this->once())->method('catch');
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Fail'));

        $processor->process($client);
    }

    #[Test]
    public function processCallsTransactionFinallyOnSuccess(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->expects($this->once())->method('finally');
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $processor->process($client);
    }

    #[Test]
    public function processCallsTransactionFinallyOnFailure(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->expects($this->once())->method('finally');
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Fail'));

        $processor->process($client);
    }

    #[Test]
    public function processSetsStatusToCanceledWhenBeginThrows(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->method('begin')->willThrowException(new \RuntimeException('Begin failed'));
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertSame(TransactionStatus::CANCELED, $result->getTransactionStatus());
        $this->assertNotNull($result->getTransactionError());
    }

    #[Test]
    public function processHandlesExceptionInTransactionThenGracefully(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->method('then')->willThrowException(new \RuntimeException('Then failed'));
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertSame(TransactionStatus::SUCCESS, $result->getTransactionStatus());
    }

    #[Test]
    public function processHandlesExceptionInTransactionCatchGracefully(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->method('catch')->willThrowException(new \RuntimeException('Catch failed'));
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Network error'));

        $result = $processor->process($client);

        $this->assertSame(TransactionStatus::FAILED, $result->getTransactionStatus());
    }

    #[Test]
    public function processHandlesExceptionInTransactionFinallyGracefully(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $mockStack->method('finally')->willThrowException(new \RuntimeException('Finally failed'));
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertSame(TransactionStatus::SUCCESS, $result->getTransactionStatus());
    }

    #[Test]
    public function processWithLoggerLogsWarningOnClientFailure(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('warning');

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Fail'));

        $processor->process($client, $logger);
    }

    #[Test]
    public function processWithLoggerLogsWarningWhenBeginFails(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(true);
        $mockStack->method('begin')->willThrowException(new \RuntimeException('Begin failed'));
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('warning');

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $processor->process($client, $logger);
    }

    #[Test]
    public function processReturnsSelfInstance(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertSame($processor, $result);
    }

    #[Test]
    public function processSuccessResponseContainsCorrectTransaction(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $response = $this->createMock(ResponseInterface::class);
        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($response);

        $result = $processor->process($client);

        $this->assertSame($transaction, $result->getTransactionResponse()->getTransaction());
        $this->assertSame($transaction->getState(), $processor->getTransaction()->getState());
    }

    #[Test]
    public function processFailedErrorContainsCorrectTransaction(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new \RuntimeException('Fail'));

        $result = $processor->process($client);

        $this->assertSame($transaction, $result->getTransactionError()->getTransaction());
    }

    #[Test]
    public function processExceptionInCatchShouldSilentWithReturnSameProcessor(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new class('txn_1', GatewayAction::CHARGE, [], $mockStack) extends Transaction {
            public function begin(TransactionProcessorInterface $processor, ?LoggerInterface $logger = null): void
            {
                throw new \RuntimeException('Begin failed');
            }

            public function catch(
                TransactionErrorInterface $error,
                TransactionProcessorInterface $processor,
                ?LoggerInterface $logger = null
            ): void {
                throw new \RuntimeException('Catch failed');
            }
        };

        $processor = $this->createProcessor($transaction);

        $client = $this->createMock(ClientInterface::class);

        $result = $processor->process($client);

        $this->assertSame($result, $processor);
    }

    #[Test]
    public function processExceptionIfIsNotPendingReturningEarly(): void
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getName')->willReturn('TestGateway');
        $request = $this->createMock(RequestInterface::class);

        $processor = new class(
            $gateway,
            $transaction,
            $request
        ) extends TransactionProcessor {
            public function getTransactionStatus(): TransactionStatus
            {
                return TransactionStatus::PROCESSING;
            }
        };

        $client = $this->createMock(ClientInterface::class);

        $result = $processor->process($client);

        $this->assertSame($result, $processor);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function gatewayPropertyIsReadonly(): void
    {
        $processor = $this->createProcessor();

        $reflection = new ReflectionProperty($processor, 'gateway');

        $this->assertTrue($reflection->isReadOnly());
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function transactionPropertyIsReadonly(): void
    {
        $processor = $this->createProcessor();

        $reflection = new ReflectionProperty($processor, 'transaction');

        $this->assertTrue($reflection->isReadOnly());
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function requestPropertyIsReadonly(): void
    {
        $processor = $this->createProcessor();

        $reflection = new ReflectionProperty($processor, 'request');

        $this->assertTrue($reflection->isReadOnly());
    }

    private function createProcessor(?TransactionInterface $transaction = null): TransactionProcessor
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->method('getName')->willReturn('TestGateway');
        $transaction = $transaction ?? new Transaction('txn_1', GatewayAction::CHARGE);
        $request = $this->createMock(RequestInterface::class);

        return new TransactionProcessor($gateway, $transaction, $request);
    }
}
