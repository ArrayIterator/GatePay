<?php
declare(strict_types=1);

namespace GatePay\Example\DummyGateway\Actions;

use GatePay\Core\Abstracts\AbstractGatewayAction;
use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Exceptions\UnsupportedActionException;
use GatePay\Core\Interfaces\GatewayInterface;
use GatePay\Core\Interfaces\TransactionInterface;
use GatePay\Example\DummyGateway\DummyGateway;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use function array_filter;
use function http_build_query;
use function is_numeric;

class TestAction extends AbstractGatewayAction
{
    /**
     * @inheritdoc
     */
    public function isProcessable(TransactionInterface $transaction): bool
    {
        if ($transaction->getAction() !== $this->getAction()) {
            return false;
        }
        $params = $transaction->getParameters();
        return ($params['test_param']??null) === 'test_value';
    }

    /**
     * @inheritdoc
     */
    public function getAction(): GatewayAction
    {
        return GatewayAction::TEST;
    }

    /**
     * Creates an HTTP request for the given transaction using the provided request factory.
     * The request is constructed with the transaction parameters and includes
     * a custom header indicating the action type.
     * The method first checks if the transaction is processable for this action,
     * and if not, it throws an UnsupportedActionException.
     * @see parent::assertProcessable()
     *      for details on the checks performed to ensure the transaction is valid for this action.
     * @inheritdoc
     */
    public function createRequest(
        GatewayInterface $gateway,
        TransactionInterface $transaction,
        RequestFactoryInterface $requestFactory,
        ?LoggerInterface $logger = null
    ): RequestInterface {
        // Ensure that the transaction is processable for this action and that the gateway is supported.
        $this->assertProcessable($gateway, $transaction);
        /**
         * @var array{
         *     test_param: string,
         *     amount: float,
         *     currency: string,
         * } $params
         */
        $params = $transaction->getParameters();
        if (!is_numeric($params['amount']??null) || !is_string($params['currency']??null)) {
            throw new UnsupportedActionException(
                $gateway,
                $transaction->getAction(),
                'Invalid parameters for TestAction. Expected amount (numeric) and currency (string).'
            );
        }
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
