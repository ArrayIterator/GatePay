<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use Closure;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\SourceType;
use GatePay\Core\Enum\TransactionState;
use GatePay\Core\Enum\TransactionStatus;
use GatePay\Core\Interfaces\TransactionErrorInterface;
use GatePay\Core\Interfaces\TransactionProcessorInterface;
use GatePay\Core\Interfaces\TransactionResponseInterface;
use GatePay\Core\Interfaces\TransactionStackInterface;
use GatePay\Core\Transaction;
use GatePay\Core\TransactionStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class TransactionStackTest extends TestCase
{
    #[Test]
    public function initialStateIsPending(): void
    {
        [, $stack] = $this->createTransactionAndStack();

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function initialTimestampIsZero(): void
    {
        [, $stack] = $this->createTransactionAndStack();

        $this->assertSame(0, $stack->getTimestamp());
    }

    #[Test]
    public function loggingIsEnabledByDefault(): void
    {
        [, $stack] = $this->createTransactionAndStack();

        $this->assertTrue($stack->isEnableLogging());
    }

    #[Test]
    public function setEnableLoggingToFalseDisablesLogging(): void
    {
        [, $stack] = $this->createTransactionAndStack();

        $stack->setEnableLogging(false);

        $this->assertFalse($stack->isEnableLogging());
    }

    #[Test]
    public function setEnableLoggingBackToTrueReEnablesLogging(): void
    {
        [, $stack] = $this->createTransactionAndStack();

        $stack->setEnableLogging(false);
        $stack->setEnableLogging(true);

        $this->assertTrue($stack->isEnableLogging());
    }

    #[Test]
    public function transactionResultDataIsNullInitially(): void
    {
        [, $stack] = $this->createTransactionAndStack();

        $this->assertNull($stack->getTransactionResultData());
    }

    // --- begin() ---

    #[Test]
    public function beginTransitionsStateToBegin(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $processor = $this->createMockProcessor($transaction);
        $this->driveTransactionToBegin($transaction);

        $stack->begin($processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function beginSkipsWhenTransactionStateIsNotBegin(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $processor = $this->createMockProcessor($transaction);

        $stack->begin($processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function beginSkipsWhenAlreadyInBeginState(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $processor = $this->createMockProcessor($transaction);
        $this->driveTransactionToBegin($transaction);
        $stack->begin($processor);

        $stack->begin($processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function beginSkipsWhenProcessorTransactionDoesNotMatch(): void
    {
        [, $stack] = $this->createTransactionAndStack();
        $otherTransaction = $this->createSilentTransaction('txn_other');
        $processor = $this->createMockProcessor($otherTransaction);

        $stack->begin($processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function beginLogsWarningOnTransactionMismatch(): void
    {
        [, $stack] = $this->createTransactionAndStack();
        $otherTransaction = $this->createSilentTransaction('txn_manipulated');
        $processor = $this->createMockProcessor($otherTransaction);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')
            ->with($this->stringContains('does not match'));

        $stack->begin($processor, $logger);
    }

    #[Test]
    public function beginDoesNotLogWarningOnMismatchWhenLoggingDisabled(): void
    {
        [, $stack] = $this->createTransactionAndStack();
        $stack->setEnableLogging(false);
        $otherTransaction = $this->createSilentTransaction('txn_manipulated');
        $processor = $this->createMockProcessor($otherTransaction);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $stack->begin($processor, $logger);
    }

    // --- then() ---

    #[Test]
    public function thenTransitionsStateToSuccess(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $this->driveTransactionToSuccess($transaction);
        $processor = $this->createMockProcessor($transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        $stack->then($response, $processor);

        $this->assertSame(TransactionState::SUCCESS, $stack->getState());
    }

    #[Test]
    public function thenSkipsWhenStackIsNotInBeginState(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->driveTransactionToSuccess($transaction);
        $processor = $this->createMockProcessor($transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        $stack->then($response, $processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function thenSkipsWhenProcessorTransactionDoesNotMatch(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $otherTransaction = $this->createSilentTransaction('txn_manipulated');
        $processor = $this->createMockProcessor($otherTransaction);
        $response = $this->createMock(TransactionResponseInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $stack->then($response, $processor, $logger);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function thenSkipsWhenTransactionStateIsNotSuccess(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $processor = $this->createMockProcessor($transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        $stack->then($response, $processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    // --- catch() ---

    #[Test]
    public function catchTransitionsStateToError(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $this->driveTransactionToError($transaction);
        $processor = $this->createMockProcessor($transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $stack->catch($error, $processor);

        $this->assertSame(TransactionState::ERROR, $stack->getState());
    }

    #[Test]
    public function catchSkipsWhenStackIsNotInBeginState(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->driveTransactionToError($transaction);
        $processor = $this->createMockProcessor($transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $stack->catch($error, $processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function catchSkipsWhenProcessorTransactionDoesNotMatch(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $otherTransaction = $this->createSilentTransaction('txn_manipulated');
        $processor = $this->createMockProcessor($otherTransaction);
        $error = $this->createMock(TransactionErrorInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $stack->catch($error, $processor, $logger);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function catchSkipsWhenTransactionStateIsNotError(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $processor = $this->createMockProcessor($transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $stack->catch($error, $processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    // --- finally() ---

    #[Test]
    public function finallyTransitionsToFinalFromSuccess(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $processor = $this->createMockProcessor($transaction);

        $stack->finally($processor);

        $this->assertSame(TransactionState::FINAL, $stack->getState());
    }

    #[Test]
    public function finallyTransitionsToFinalFromError(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToError($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::FAILED);
        $processor = $this->createMockProcessor($transaction);

        $stack->finally($processor);

        $this->assertSame(TransactionState::FINAL, $stack->getState());
    }

    #[Test]
    public function finallySkipsWhenStackIsInPendingState(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $processor = $this->createMockProcessor($transaction);

        $stack->finally($processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function finallySkipsWhenStackIsInBeginState(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $processor = $this->createMockProcessor($transaction);

        $stack->finally($processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function finallySkipsWhenProcessorTransactionDoesNotMatch(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $otherTransaction = $this->createSilentTransaction('txn_manipulated');
        $processor = $this->createMockProcessor($otherTransaction);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $stack->finally($processor, $logger);

        $this->assertSame(TransactionState::SUCCESS, $stack->getState());
    }

    #[Test]
    public function finallySkipsWhenTransactionStateIsNotFinal(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $processor = $this->createMockProcessor($transaction);

        $stack->finally($processor);

        $this->assertSame(TransactionState::SUCCESS, $stack->getState());
    }

    // --- finally() response parsing ---

    #[Test]
    public function finallyParsesJsonResponseIntoFrozenResultData(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('application/json', '{"status":"ok","amount":1000}');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $resultData = $stack->getTransactionResultData();
        $this->assertNotNull($resultData);
        $this->assertSame('ok', $resultData->get('status'));
        $this->assertSame(1000, $resultData->get('amount'));
        $this->assertTrue($resultData->isFrozen());
        $this->assertSame(SourceType::JSON, $resultData->getSourceType());
    }

    #[Test]
    public function finallyParsesJsonWithCharsetContentType(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('application/json; charset=utf-8', '{"key":"value"}');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $resultData = $stack->getTransactionResultData();
        $this->assertNotNull($resultData);
        $this->assertSame('value', $resultData->get('key'));
    }

    #[Test]
    public function finallyParsesXmlResponseIntoFrozenResultData(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?><response><status>ok</status></response>';
        $httpResponse = $this->createMockHttpResponse('application/xml', $xmlBody);
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $resultData = $stack->getTransactionResultData();
        $this->assertNotNull($resultData);
        $this->assertTrue($resultData->isFrozen());
        $this->assertSame(SourceType::XML, $resultData->getSourceType());
    }

    #[Test]
    public function finallyReturnsNullResultDataForUnknownContentType(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('text/plain', 'some text');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $this->assertNull($stack->getTransactionResultData());
    }

    #[Test]
    public function finallyReturnsNullResultDataForEmptyContentType(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('', '{}');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $this->assertNull($stack->getTransactionResultData());
    }

    #[Test]
    public function finallyReturnsNullResultDataWhenNoResponse(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn(null);

        $stack->finally($processor);

        $this->assertSame(TransactionState::FINAL, $stack->getState());
        $this->assertNull($stack->getTransactionResultData());
    }

    #[Test]
    public function finallyHandlesBodyReadErrorGracefully(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willThrowException(new \RuntimeException('Read error'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $httpResponse->method('getBody')->willReturn($body);
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $this->assertSame(TransactionState::FINAL, $stack->getState());
        $this->assertNull($stack->getTransactionResultData());
    }

    #[Test]
    public function finallyHandlesInvalidJsonGracefully(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('application/json', '{invalid json!!!');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $this->assertNull($stack->getTransactionResultData());
    }

    #[Test]
    public function finallyHandlesInvalidXmlGracefully(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('application/xml', '<<<not xml>>>');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);

        $stack->finally($processor);

        $this->assertNull($stack->getTransactionResultData());
    }

    #[Test]
    public function finallyLogsErrorOnBodyReadFailure(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willThrowException(new \RuntimeException('stream broken'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $httpResponse->method('getBody')->willReturn($body);
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $stack->finally($processor, $logger);
    }

    #[Test]
    public function finallyLogsErrorOnInvalidJsonDecode(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $httpResponse = $this->createMockHttpResponse('application/json', '{{bad');
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $stack->finally($processor, $logger);
    }

    #[Test]
    public function finallyDoesNotLogErrorWhenLoggingDisabledOnBodyReadFailure(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $stack->setEnableLogging(false);
        $this->advanceStackToSuccess($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willThrowException(new \RuntimeException('stream broken'));
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getHeaderLine')->with('Content-Type')->willReturn('application/json');
        $httpResponse->method('getBody')->willReturn($body);
        $transactionResponse = $this->createMock(TransactionResponseInterface::class);
        $transactionResponse->method('getResponse')->willReturn($httpResponse);
        $processor = $this->createMockProcessor($transaction);
        $processor->method('getTransactionResponse')->willReturn($transactionResponse);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $stack->finally($processor, $logger);
    }

    #[Test]
    public function finallyPrefersProcessorResponseOverStackResponse(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $this->driveTransactionToSuccess($transaction);

        $stackHttpResponse = $this->createMockHttpResponse('application/json', '{"source":"stack"}');
        $stackTransactionResponse = $this->createMock(TransactionResponseInterface::class);
        $stackTransactionResponse->method('getResponse')->willReturn($stackHttpResponse);
        $thenProcessor = $this->createMockProcessor($transaction);
        $stack->then($stackTransactionResponse, $thenProcessor);

        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $processorHttpResponse = $this->createMockHttpResponse('application/json', '{"source":"processor"}');
        $processorTransactionResponse = $this->createMock(TransactionResponseInterface::class);
        $processorTransactionResponse->method('getResponse')->willReturn($processorHttpResponse);
        $finalProcessor = $this->createMockProcessor($transaction);
        $finalProcessor->method('getTransactionResponse')->willReturn($processorTransactionResponse);

        $stack->finally($finalProcessor);

        $resultData = $stack->getTransactionResultData();
        $this->assertNotNull($resultData);
        $this->assertSame('processor', $resultData->get('source'));
    }

    #[Test]
    public function finallyFallsBackToThenResponseWhenProcessorResponseIsNull(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $this->driveTransactionToSuccess($transaction);

        $stackHttpResponse = $this->createMockHttpResponse('application/json', '{"source":"then"}');
        $stackTransactionResponse = $this->createMock(TransactionResponseInterface::class);
        $stackTransactionResponse->method('getResponse')->willReturn($stackHttpResponse);
        $thenProcessor = $this->createMockProcessor($transaction);
        $stack->then($stackTransactionResponse, $thenProcessor);

        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);

        $finalProcessor = $this->createMockProcessor($transaction);
        $finalProcessor->method('getTransactionResponse')->willReturn(null);

        $stack->finally($finalProcessor);

        $resultData = $stack->getTransactionResultData();
        $this->assertNotNull($resultData);
        $this->assertSame('then', $resultData->get('source'));
    }

    // --- Full flow ---

    #[Test]
    public function fullSuccessFlowTransitionsCorrectly(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();

        $this->assertSame(TransactionState::PENDING, $stack->getState());

        $this->advanceStackToBegin($transaction, $stack);
        $this->assertSame(TransactionState::BEGIN, $stack->getState());

        $this->advanceStackToSuccess($transaction, $stack);
        $this->assertSame(TransactionState::SUCCESS, $stack->getState());

        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $finalProcessor = $this->createMockProcessor($transaction);
        $stack->finally($finalProcessor);
        $this->assertSame(TransactionState::FINAL, $stack->getState());
    }

    #[Test]
    public function fullErrorFlowTransitionsCorrectly(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();

        $this->assertSame(TransactionState::PENDING, $stack->getState());

        $this->advanceStackToBegin($transaction, $stack);
        $this->assertSame(TransactionState::BEGIN, $stack->getState());

        $this->advanceStackToError($transaction, $stack);
        $this->assertSame(TransactionState::ERROR, $stack->getState());

        $this->driveTransactionToFinal($transaction, TransactionStatus::FAILED);
        $finalProcessor = $this->createMockProcessor($transaction);
        $stack->finally($finalProcessor);
        $this->assertSame(TransactionState::FINAL, $stack->getState());
    }

    // --- Manipulation detection across all lifecycle methods ---

    #[Test]
    public function manipulatedProcessorOnBeginIsRejected(): void
    {
        [,$stack] = $this->createTransactionAndStack();
        $manipulated = $this->createSilentTransaction('txn_fake');
        $processor = $this->createMockProcessor($manipulated);

        $stack->begin($processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function manipulatedProcessorOnThenIsRejected(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $manipulated = $this->createSilentTransaction('txn_fake');
        $processor = $this->createMockProcessor($manipulated);
        $response = $this->createMock(TransactionResponseInterface::class);

        $stack->then($response, $processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function manipulatedDifferentTransaction(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $manipulated = $this->createSilentTransaction('txn_fake');
        $processor = $this->createMockProcessor($manipulated);
        $httpResponse = $this->createMockHttpResponse('application/json', '{"status":"ok","amount":1000}');
        $result = Closure::bind(
            function ($response, $processor) {
                $this->parseDataFromResponse($response, $processor);
            },
            $stack,
            TransactionStack::class
        )($httpResponse, $processor);
        $this->assertSame(null, $result);
    }

    #[Test]
    public function manipulatedProcessorOnCatchIsRejected(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $manipulated = $this->createSilentTransaction('txn_fake');
        $processor = $this->createMockProcessor($manipulated);
        $error = $this->createMock(TransactionErrorInterface::class);

        $stack->catch($error, $processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function manipulatedProcessorOnFinallyIsRejected(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $manipulated = $this->createSilentTransaction('txn_fake');
        $processor = $this->createMockProcessor($manipulated);

        $stack->finally($processor);

        $this->assertSame(TransactionState::SUCCESS, $stack->getState());
    }

    #[Test]
    public function manipulatedProcessorWithSameIdButDifferentInstanceIsRejected(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToBegin($transaction, $stack);
        $sameIdDifferentInstance = $this->createSilentTransaction('txn_1');
        $processor = $this->createMockProcessor($sameIdDifferentInstance);
        $response = $this->createMock(TransactionResponseInterface::class);

        $stack->then($response, $processor);

        $this->assertSame(TransactionState::BEGIN, $stack->getState());
    }

    #[Test]
    public function manipulationDetectedOnEveryMethodLogsWarning(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $manipulated = $this->createSilentTransaction('txn_fake');
        $processor = $this->createMockProcessor($manipulated);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(4))->method('warning');

        $this->driveTransactionToBegin($transaction);
        $stack->begin($processor, $logger);

        $this->advanceStackToBegin($transaction, $stack);
        $this->driveTransactionToSuccess($transaction);
        $stack->then($this->createMock(TransactionResponseInterface::class), $processor, $logger);

        $stack->catch($this->createMock(TransactionErrorInterface::class), $processor, $logger);

        $this->advanceStackToSuccessFromBegin($transaction, $stack);
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $stack->finally($processor, $logger);
    }

    // --- Edge: cannot skip states ---

    #[Test]
    public function cannotTransitionDirectlyFromPendingToSuccess(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->driveTransactionToSuccess($transaction);
        $processor = $this->createMockProcessor($transaction);
        $response = $this->createMock(TransactionResponseInterface::class);

        $stack->then($response, $processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function cannotTransitionDirectlyFromPendingToError(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->driveTransactionToError($transaction);
        $processor = $this->createMockProcessor($transaction);
        $error = $this->createMock(TransactionErrorInterface::class);

        $stack->catch($error, $processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function cannotTransitionDirectlyFromPendingToFinal(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->driveTransactionToFinal($transaction, TransactionStatus::SUCCESS);
        $processor = $this->createMockProcessor($transaction);

        $stack->finally($processor);

        $this->assertSame(TransactionState::PENDING, $stack->getState());
    }

    #[Test]
    public function cannotTransitionFromSuccessToBegin(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToSuccess($transaction, $stack);
        $processor = $this->createMockProcessor($transaction);

        $stack->begin($processor);

        $this->assertSame(TransactionState::SUCCESS, $stack->getState());
    }

    #[Test]
    public function cannotTransitionFromErrorToBegin(): void
    {
        [$transaction, $stack] = $this->createTransactionAndStack();
        $this->advanceStackToError($transaction, $stack);
        $processor = $this->createMockProcessor($transaction);

        $stack->begin($processor);

        $this->assertSame(TransactionState::ERROR, $stack->getState());
    }

    // --- Helpers ---

    /**
     * @return array{Transaction, TransactionStack}
     */
    private function createTransactionAndStack(): array
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        $transaction = new Transaction('txn_1', GatewayAction::CHARGE, [], $mockStack);
        $stack = new TransactionStack($transaction);
        return [$transaction, $stack];
    }

    private function createSilentTransaction(string $id): Transaction
    {
        $mockStack = $this->createMock(TransactionStackInterface::class);
        $mockStack->method('isEnableLogging')->willReturn(false);
        return new Transaction($id, GatewayAction::CHARGE, [], $mockStack);
    }

    private function createMockProcessor(Transaction $transaction): TransactionProcessorInterface
    {
        $processor = $this->createMock(TransactionProcessorInterface::class);
        $processor->method('getTransaction')->willReturn($transaction);
        return $processor;
    }

    private function createMockHttpResponse(string $contentType, string $body): ResponseInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->with('Content-Type')->willReturn($contentType);
        $response->method('getBody')->willReturn($stream);
        return $response;
    }

    private function driveTransactionToBegin(Transaction $transaction): void
    {
        $processor = $this->createMock(TransactionProcessorInterface::class);
        $processor->method('getTransaction')->willReturn($transaction);
        $processor->method('getTransactionStatus')->willReturn(TransactionStatus::PROCESSING);
        $transaction->begin($processor);
    }

    private function driveTransactionToSuccess(Transaction $transaction): void
    {
        if ($transaction->getState() === TransactionState::PENDING) {
            $this->driveTransactionToBegin($transaction);
        }
        $processor = $this->createMock(TransactionProcessorInterface::class);
        $processor->method('getTransaction')->willReturn($transaction);
        $processor->method('getTransactionStatus')->willReturn(TransactionStatus::SUCCESS);
        $transaction->then($this->createMock(TransactionResponseInterface::class), $processor);
    }

    private function driveTransactionToError(Transaction $transaction): void
    {
        if ($transaction->getState() === TransactionState::PENDING) {
            $this->driveTransactionToBegin($transaction);
        }
        $processor = $this->createMock(TransactionProcessorInterface::class);
        $processor->method('getTransaction')->willReturn($transaction);
        $processor->method('getTransactionStatus')->willReturn(TransactionStatus::FAILED);
        $transaction->catch($this->createMock(TransactionErrorInterface::class), $processor);
    }

    private function driveTransactionToFinal(Transaction $transaction, TransactionStatus $status): void
    {
        if ($transaction->getState() === TransactionState::PENDING) {
            if ($status->isSuccess()) {
                $this->driveTransactionToSuccess($transaction);
            } else {
                $this->driveTransactionToError($transaction);
            }
        }
        $processor = $this->createMock(TransactionProcessorInterface::class);
        $processor->method('getTransaction')->willReturn($transaction);
        $processor->method('getTransactionStatus')->willReturn($status);
        $transaction->finally($processor);
    }

    private function advanceStackToBegin(Transaction $transaction, TransactionStack $stack): void
    {
        if ($transaction->getState() === TransactionState::PENDING) {
            $this->driveTransactionToBegin($transaction);
        }
        $processor = $this->createMockProcessor($transaction);
        $stack->begin($processor);
    }

    private function advanceStackToSuccess(Transaction $transaction, TransactionStack $stack): void
    {
        $this->advanceStackToBegin($transaction, $stack);
        $this->advanceStackToSuccessFromBegin($transaction, $stack);
    }

    private function advanceStackToSuccessFromBegin(Transaction $transaction, TransactionStack $stack): void
    {
        if ($transaction->getState() !== TransactionState::SUCCESS) {
            $this->driveTransactionToSuccess($transaction);
        }
        $processor = $this->createMockProcessor($transaction);
        $stack->then($this->createMock(TransactionResponseInterface::class), $processor);
    }

    private function advanceStackToError(Transaction $transaction, TransactionStack $stack): void
    {
        $this->advanceStackToBegin($transaction, $stack);
        if ($transaction->getState() !== TransactionState::ERROR) {
            $this->driveTransactionToError($transaction);
        }
        $processor = $this->createMockProcessor($transaction);
        $stack->catch($this->createMock(TransactionErrorInterface::class), $processor);
    }
}
