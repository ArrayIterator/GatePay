<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Exceptions;

use GatePay\Core\Exceptions\GatewayNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GatewayNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function constructorWithMessageSetsMessage(): void
    {
        $message = 'Gateway not found';
        $exception = new GatewayNotFoundException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    #[Test]
    public function constructorWithCodeSetsCode(): void
    {
        $exception = new GatewayNotFoundException('Error', 404);

        $this->assertSame(404, $exception->getCode());
    }

    #[Test]
    public function constructorWithPreviousExceptionSetsPrevious(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new GatewayNotFoundException('Error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = new GatewayNotFoundException('Test');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    #[Test]
    public function constructorWithEmptyMessageSetsEmptyMessage(): void
    {
        $exception = new GatewayNotFoundException();

        $this->assertSame('', $exception->getMessage());
    }

    #[Test]
    public function defaultCodeIsZero(): void
    {
        $exception = new GatewayNotFoundException('Test');

        $this->assertSame(0, $exception->getCode());
    }

    #[Test]
    public function defaultPreviousIsNull(): void
    {
        $exception = new GatewayNotFoundException('Test');

        $this->assertNull($exception->getPrevious());
    }
}
