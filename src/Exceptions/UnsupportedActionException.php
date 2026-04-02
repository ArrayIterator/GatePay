<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Exceptions;

use ArrayIterator\GatePay\Enum\GatewayAction;
use ArrayIterator\GatePay\Interfaces\GatewayInterface;
use RuntimeException;
use Throwable;

class UnsupportedActionException extends RuntimeException
{

    public function __construct(
        public readonly GatewayInterface $gateway,
        public readonly GatewayAction    $action,
        string                           $message = "",
        int                              $code = 0,
        ?Throwable                       $previous = null
    ) {
        $message = $message ?: "The action '{$action->value}' is not supported by the gateway '{$gateway->getName()}'.";
        parent::__construct($message, $code, $previous);
    }

    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    public function getAction(): GatewayAction
    {
        return $this->action;
    }
}
