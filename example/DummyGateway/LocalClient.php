<?php
declare(strict_types=1);

namespace GatePay\Example\DummyGateway;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use function is_numeric;
use function is_string;
use function parse_str;
use function round;
use function strlen;
use function strtoupper;

class LocalClient implements ClientInterface
{
    public function __construct(
        public readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri()->getQuery();
        parse_str($uri, $queryParams);
        $transactionId = $queryParams['transaction_id'] ?? null;
        $amount = $queryParams['amount'] ?? null;
        $currency = $queryParams['currency'] ?? null;
        if (!is_string($transactionId)) {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'application/json')
                ->withBody(
                    $this->responseFactory->createStream(
                        json_encode([
                            'status' => 'error',
                            'message' => 'Missing or invalid transaction_id in the request.'
                        ])
                    )
                );
        }
        if (!is_numeric($amount)) {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'application/json')
                ->withBody(
                    $this->responseFactory->createStream(
                        json_encode([
                            'status' => 'error',
                            'message' => 'Missing or invalid amount in the request.'
                        ])
                    )
                );
        }
        if (!is_string($currency) || trim($currency) === '') {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'application/json')
                ->withBody(
                    $this->responseFactory->createStream(
                        json_encode([
                            'status' => 'error',
                            'message' => 'Missing or invalid currency in the request.'
                        ])
                    )
                );
        }
        $currency= strtoupper($currency);
        if (strlen($currency) !== 3) {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'application/json')
                ->withBody(
                    $this->responseFactory->createStream(
                        json_encode([
                            'status' => 'error',
                            'message' => 'Currency must be a 3-letter ISO code.'
                        ])
                    )
                );
        }
        $amount = (float)$amount;
        $round = round($amount, 2);
        if ($round <= 0) {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'application/json')
                ->withBody(
                    $this->responseFactory->createStream(
                        json_encode([
                            'status' => 'error',
                            'message' => 'Amount must be greater than zero.'
                        ])
                    )
                );
        }
        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                // create a stream with the JSON content
                $this->responseFactory->createStream(
                    json_encode([
                        'status' => 'success',
                        'message' => 'This is a dummy response from LocalClient.',
                        'data' => [
                            'transaction_id' => $transactionId,
                            'amount' => $amount,
                            'currency' => $currency
                        ]
                    ])
                )
            );
    }
}
