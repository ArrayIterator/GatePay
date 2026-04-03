<?php
declare(strict_types=1);

namespace GatePay\Example\DummyGateway\Actions;

use GatePay\Core\Abstracts\AbstractGatewayAction;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Interfaces\TransactionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use function array_filter;
use function http_build_query;

class TestAction extends AbstractGatewayAction
{
    public function isProcessable(TransactionInterface $transaction): bool
    {
        if ($transaction->getAction() !== $this->getAction()) {
            return false;
        }
        $params = $transaction->getParameters();
        return ($params['test_param']??null) === 'test_value';
    }

    public function getAction(): GatewayAction
    {
        return GatewayAction::TEST;
    }

    public function createRequest(
        TransactionInterface $transaction,
        RequestFactoryInterface $requestFactory,
        ?LoggerInterface $logger = null
    ): RequestInterface {
        if (!$this->isProcessable($transaction)) {
            throw new UnsupportedActionException(
                $this->getGateway(),
                $this->getAction(),
                'The transaction is not processable for this action.',
            );
        }
        $params = $transaction->getParameters();
        $params = array_filter($params, 'is_scalar');
        $params['transaction_id'] = $transaction->getTransactionId();
        return $requestFactory
            ->createRequest(
                'GET',
                'https://localhost/test?transaction_id=' . http_build_query($params)
            )->withHeader(
                'X-Action',
                $this->getAction()->value
            );
    }
}