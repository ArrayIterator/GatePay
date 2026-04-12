<?php
declare(strict_types=1);

namespace GatePay\CoreTests\Depends;

use GatePay\Core\Abstracts\AbstractGatewayAction;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class ConcreteTestAction extends AbstractGatewayAction
{
    public function getAction(): GatewayAction
    {
        return GatewayAction::TEST;
    }

    public function createRequest(
        GatewayInterface                               $gateway,
        TransactionInterface                           $transaction,
        RequestFactoryInterface&StreamFactoryInterface $factory,
        ?LoggerInterface                               $logger = null
    ): RequestInterface {
        return $factory->createRequest('GET', 'https://test.example.com');
    }
}
