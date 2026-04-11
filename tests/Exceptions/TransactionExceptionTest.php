<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Exceptions;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\TransactionException;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Transaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TransactionExceptionTest extends TestCase
{
    private TransactionInterface $transaction;

    protected function setUp(): void
    {
        $this->transaction = new Transaction(
            'txn_123456',
            GatewayAction::CHARGE,
            ['amount' => 1000]
        );
    }

    #[Test]
    public function constructorSetsTransaction(): void
    {
        $exception = new TransactionException($this->transaction);

        $this->assertSame($this->transaction, $exception->getTransaction());
        $this->assertSame($this->transaction, $exception->transaction);
    }

    #[Test]
    public function constructorWithMessageSetsMessage(): void
    {
        $message = 'Transaction failed';
        $exception = new TransactionException($this->transaction, $message);

        $this->assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function constructorWithCodeSetsCode(): void
    {
        $exception = new TransactionException($this->transaction, 'Error', 500);

        $this->assertSame(500, $exception->getCode());
    }

    #[Test]
    public function constructorWithPreviousExceptionSetsPrevious(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new TransactionException($this->transaction, 'Error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = new TransactionException($this->transaction);

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    #[Test]
    public function getTransactionReturnsCorrectTransaction(): void
    {
        $exception = new TransactionException($this->transaction, 'Test message', 100);

        $returnedTransaction = $exception->getTransaction();

        $this->assertSame($this->transaction, $returnedTransaction);
        $this->assertSame('txn_123456', $returnedTransaction->getTransactionId());
        $this->assertSame(GatewayAction::CHARGE, $returnedTransaction->getAction());
    }

    #[Test]
    public function transactionPropertyIsReadonly(): void
    {
        $exception = new TransactionException($this->transaction);

        $reflection = new \ReflectionProperty($exception, 'transaction');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function constructorWithAllParametersSetsAllProperties(): void
    {
        $message = 'Complete error message';
        $code = 503;
        $previous = new RuntimeException('Inner exception');

        $exception = new TransactionException($this->transaction, $message, $code, $previous);

        $this->assertSame($this->transaction, $exception->getTransaction());
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function defaultMessageIsEmpty(): void
    {
        $exception = new TransactionException($this->transaction);

        $this->assertSame('', $exception->getMessage());
    }

    #[Test]
    public function defaultCodeIsZero(): void
    {
        $exception = new TransactionException($this->transaction);

        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function defaultPreviousIsNull(): void
    {
        $exception = new TransactionException($this->transaction);

        $this->assertNull($exception->getPrevious());
    }
}
