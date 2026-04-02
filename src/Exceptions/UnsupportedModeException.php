<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Exceptions;

use ArrayIterator\GatePay\Interfaces\GatewayInterface;
use RuntimeException;
use Throwable;

/**
 * Exception thrown when a gateway does not support a specific mode of operation.
 * @template Mode of string
 */
class UnsupportedModeException extends RuntimeException
{
    /**
     * UnsupportedModeException constructor.
     *
     * @param GatewayInterface $gateway The gateway that does not support the mode.
     * @param Mode $mode The unsupported mode.
     * @param string $message Optional custom error message.
     * @param int $code Optional error code.
     * @param Throwable|null $previous Optional previous exception for chaining.
     */
    public function __construct(
        public readonly GatewayInterface $gateway,
        public readonly string $mode,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $message = $message ?: "The mode '{$mode}' is not supported by the gateway '{$gateway->getName()}'.";
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return GatewayInterface
     */
    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    /**
     * @return Mode
     */
    public function getMode(): string
    {
        return $this->mode;
    }
}
