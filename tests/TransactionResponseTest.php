<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\TransactionStatus;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Core\Transaction;
use GatePay\Core\TransactionResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class TransactionResponseTest extends TestCase
{
    private TransactionInterface $transaction;

    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->transaction = new Transaction(
            'txn_123456',
            GatewayAction::CHARGE,
            ['amount' => 1000]
        );
        $this->response = $this->createMock(ResponseInterface::class);
    }

    #[Test]
    public function constructorSetsTransaction(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $this->assertSame($this->transaction, $transactionResponse->transaction);
        $this->assertSame($this->transaction, $transactionResponse->getTransaction());
    }

    #[Test]
    public function constructorSetsStatus(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $this->assertSame(TransactionStatus::SUCCESS, $transactionResponse->status);
        $this->assertSame(TransactionStatus::SUCCESS, $transactionResponse->getStatus());
    }

    #[Test]
    public function constructorSetsResponse(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $this->assertSame($this->response, $transactionResponse->response);
        $this->assertSame($this->response, $transactionResponse->getResponse());
    }

    #[Test]
    public function getTransactionReturnsCorrectTransaction(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $returnedTransaction = $transactionResponse->getTransaction();

        $this->assertSame('txn_123456', $returnedTransaction->getTransactionId());
        $this->assertSame(GatewayAction::CHARGE, $returnedTransaction->getAction());
    }

    #[Test]
    public function getStatusReturnsSuccessStatus(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $this->assertSame(TransactionStatus::SUCCESS, $transactionResponse->getStatus());
        $this->assertTrue($transactionResponse->getStatus()->isSuccess());
    }

    #[Test]
    public function getStatusReturnsFailedStatus(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::FAILED,
            $this->response
        );

        $this->assertSame(TransactionStatus::FAILED, $transactionResponse->getStatus());
        $this->assertTrue($transactionResponse->getStatus()->isFailed());
    }

    #[Test]
    public function getStatusReturnsPendingStatus(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::PENDING,
            $this->response
        );

        $this->assertSame(TransactionStatus::PENDING, $transactionResponse->getStatus());
        $this->assertTrue($transactionResponse->getStatus()->isPending());
    }

    #[Test]
    public function getStatusReturnsCanceledStatus(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::CANCELED,
            $this->response
        );

        $this->assertSame(TransactionStatus::CANCELED, $transactionResponse->getStatus());
        $this->assertTrue($transactionResponse->getStatus()->isCanceled());
    }

    #[Test]
    public function getStatusReturnsProcessingStatus(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::PROCESSING,
            $this->response
        );

        $this->assertSame(TransactionStatus::PROCESSING, $transactionResponse->getStatus());
        $this->assertTrue($transactionResponse->getStatus()->isProcessing());
    }

    #[Test]
    public function transactionPropertyIsReadonly(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $reflection = new \ReflectionProperty($transactionResponse, 'transaction');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function statusPropertyIsReadonly(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $reflection = new \ReflectionProperty($transactionResponse, 'status');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function responsePropertyIsReadonly(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $reflection = new \ReflectionProperty($transactionResponse, 'response');

        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function supportsAllTransactionStatuses(): void
    {
        $statuses = TransactionStatus::cases();

        foreach ($statuses as $status) {
            $transactionResponse = new TransactionResponse(
                $this->transaction,
                $status,
                $this->response
            );
            $this->assertSame($status, $transactionResponse->getStatus());
        }
    }

    #[Test]
    public function responseCanBeAccessedMultipleTimes(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $first = $transactionResponse->getResponse();
        $second = $transactionResponse->getResponse();
        $third = $transactionResponse->getResponse();

        $this->assertSame($first, $second);
        $this->assertSame($second, $third);
    }

    #[Test]
    public function implementsTransactionResponseInterface(): void
    {
        $transactionResponse = new TransactionResponse(
            $this->transaction,
            TransactionStatus::SUCCESS,
            $this->response
        );

        $this->assertInstanceOf(
            \GatePay\Core\Interfaces\TransactionResponseInterface::class,
            $transactionResponse
        );
    }
}
