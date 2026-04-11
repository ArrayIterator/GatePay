<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Exceptions;

use GatePay\Core\Exceptions\DataFrozenException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DataFrozenExceptionTest extends TestCase
{
    #[Test]
    public function constructorWithDefaultMessageSetsDefaultMessage(): void
    {
        $exception = new DataFrozenException();

        $this->assertSame('The data is frozen and cannot be modified.', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    #[Test]
    public function constructorWithCustomMessageSetsCustomMessage(): void
    {
        $customMessage = 'Custom frozen message';
        $exception = new DataFrozenException($customMessage);

        $this->assertSame($customMessage, $exception->getMessage());
    }

    #[Test]
    public function constructorWithEmptyMessageSetsDefaultMessage(): void
    {
        $exception = new DataFrozenException('');

        $this->assertSame('The data is frozen and cannot be modified.', $exception->getMessage());
    }

    #[Test]
    public function constructorWithCodeSetsCode(): void
    {
        $exception = new DataFrozenException('Test', 500);

        $this->assertSame(500, $exception->getCode());
    }

    #[Test]
    public function constructorWithPreviousExceptionSetsPrevious(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new DataFrozenException('Test', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = new DataFrozenException();

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}
