<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\TransactionException;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Transaction;
use GatePay\Core\TransactionError;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TransactionErrorTest extends TestCase
{
    private TransactionInterface $transaction;

    private TransactionException $exception;

    protected function setUp(): void
    {
        $this->transaction = new Transaction(
            'txn_123456',
            GatewayAction::CHARGE,
            ['amount' => 1000]
        );
        $this->exception = new TransactionException(
            $this->transaction,
            'Payment failed',
            500
        );
    }

    #[Test]
    public function constructorSetsException(): void
    {
        $error = new TransactionError($this->exception);

        $this->assertSame($this->exception, $error->exception);
        $this->assertSame($this->exception, $error->getException());
    }

    #[Test]
    public function createFactoryMethodReturnsNewInstance(): void
    {
        $error = TransactionError::create($this->exception);

        $this->assertInstanceOf(TransactionError::class, $error);
        $this->assertSame($this->exception, $error->getException());
    }

    #[Test]
    public function createFromTransactionCreatesErrorFromTransaction(): void
    {
        $originalException = new RuntimeException('Original error', 404);

        $error = TransactionError::createFromTransaction($this->transaction, $originalException);

        $this->assertInstanceOf(TransactionError::class, $error);
        $this->assertSame($this->transaction, $error->getTransaction());
        $this->assertSame('Original error', $error->getException()->getMessage());
        $this->assertSame(404, $error->getException()->getCode());
        $this->assertSame($originalException, $error->getException()->getPrevious());
    }

    #[Test]
    public function getTransactionReturnsTransactionFromException(): void
    {
        $error = new TransactionError($this->exception);

        $this->assertSame($this->transaction, $error->getTransaction());
    }

    #[Test]
    public function getExceptionReturnsStoredException(): void
    {
        $error = new TransactionError($this->exception);

        $this->assertSame($this->exception, $error->getException());
    }

    #[Test]
    public function isHttpErrorReturnsFalseForRegularException(): void
    {
        $error = new TransactionError($this->exception);

        $this->assertFalse($error->isHttpError());
    }

    #[Test]
    public function isHttpErrorReturnsTrueWhenPreviousIsRequestException(): void
    {
        $request = new Request('POST', 'http://example.com');
        $requestException = new RequestException('HTTP Error', $request);

        $transactionException = new TransactionException(
            $this->transaction,
            'HTTP request failed',
            0,
            $requestException
        );

        $error = new TransactionError($transactionException);

        $this->assertTrue($error->isHttpError());
    }

    #[Test]
    public function isHttpErrorReturnsFalseWhenPreviousIsNotRequestException(): void
    {
        $runtimeException = new RuntimeException('Some runtime error');

        $transactionException = new TransactionException(
            $this->transaction,
            'Transaction failed',
            0,
            $runtimeException
        );

        $error = new TransactionError($transactionException);

        $this->assertFalse($error->isHttpError());
    }

    #[Test]
    public function isHttpErrorReturnsFalseWhenNoPreviousException(): void
    {
        $transactionException = new TransactionException(
            $this->transaction,
            'Transaction failed'
        );

        $error = new TransactionError($transactionException);

        $this->assertFalse($error->isHttpError());
    }

    #[Test]
    public function exceptionPropertyIsReadonly(): void
    {
        $error = new TransactionError($this->exception);

        $reflection = new \ReflectionProperty($error, 'exception');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function createFromTransactionPreservesTransactionProperties(): void
    {
        $originalException = new RuntimeException('Error');

        $error = TransactionError::createFromTransaction($this->transaction, $originalException);
        $returnedTransaction = $error->getTransaction();

        $this->assertSame('txn_123456', $returnedTransaction->getTransactionId());
        $this->assertSame(GatewayAction::CHARGE, $returnedTransaction->getAction());
        $this->assertSame(['amount' => 1000], $returnedTransaction->getParameters());
    }

    #[Test]
    public function createFromTransactionWithCodeZero(): void
    {
        $originalException = new RuntimeException('Error with code zero', 0);

        $error = TransactionError::createFromTransaction($this->transaction, $originalException);

        $this->assertSame(0, $error->getException()->getCode());
    }

    #[Test]
    public function createFromTransactionWithEmptyMessage(): void
    {
        $originalException = new RuntimeException('');

        $error = TransactionError::createFromTransaction($this->transaction, $originalException);

        $this->assertSame('', $error->getException()->getMessage());
    }

    #[Test]
    public function createFromTransactionWithNestedExceptions(): void
    {
        $innerException = new RuntimeException('Inner error');
        $middleException = new RuntimeException('Middle error', 0, $innerException);

        $error = TransactionError::createFromTransaction($this->transaction, $middleException);

        $this->assertSame($middleException, $error->getException()->getPrevious());
        $this->assertSame($innerException, $error->getException()->getPrevious()->getPrevious());
    }

    #[Test]
    public function multipleCreateCallsReturnDifferentInstances(): void
    {
        $error1 = TransactionError::create($this->exception);
        $error2 = TransactionError::create($this->exception);

        $this->assertNotSame($error1, $error2);
        $this->assertSame($error1->getException(), $error2->getException());
    }
}
