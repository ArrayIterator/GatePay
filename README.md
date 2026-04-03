# GatePay - The Payment Gateway Registry

Payment Gateway Registry based on **`Deterministic`** concept,
where the transaction flow clearly serialized and deterministic,
allowing for better error handling, retry mechanisms, preventing and easier debugging.

Using PSR-7 and PSR-18 for HTTP client and message interfaces,
ensuring interoperability and flexibility in choosing HTTP client implementations.


## Utilities

- For Product Reference Order Generation [**ReferenceOrderId**](src/Utils/README.md#reference-generator) - Optimized for payment gateway use cases with prefix support and sufficient entropy, while maintaining k-sortable properties.

- For XML Parsing utility [**XMLParserArray**](src/Utils/README.md#xml-parser) - Supports SimpleXML, LibXML, and Pure PHP parsers with consistent output format.


## Integration

Link: https://github.com/ArrayIterator/credit-card

Note: Default transaction stack [TransactionStack.php](src/TransactionStack.php)

```php
<?php
declare(strict_types=1);

use GatePay\Core\CreditCard;
use GatePay\Core\Enum\TransactionState;
use GatePay\Core\GatewayRegistry;
use GatePay\Core\Transaction;
use GatePay\Core\Utils\ReferenceOrderId;

// 1. Generate Order ID
$orderIdGen = new ReferenceOrderId('PYMT');
$orderId = $orderIdGen->generate(); // PYMT-019d43d20eb8-6a5a7925dfb5

$pan = '4111111111111111';
// 2. Validate Credit Card before process
$card = new CreditCard();
$cardType = $card->guess($pan); 

$registry = new GatewayRegistry();
// register custom gateway implementation,
// this can be done in a service provider or bootstrap file
$registry->add(new MyCustomGateway(), 'MyCustomGateway');
// 3. Create Transaction
$transaction = new Transaction(
    transactionId: $orderId,
    action: GatewayAction::CHARGE,
    parameters: [
        'amount' => 100000,
        'currency' => 'IDR',
        'card_number' => $pan,
        'card_brand' => $cardType->getId(), // visa
        'card_type' => $cardType->getType(), // credit/debit
    ],
    // by default transaction stack using default
);
// create PSR-18 HTTP client and PSR-17 HTTP factory
$httpClient = new \GuzzleHttp\Client();
// PSR-17 HTTP Factory for creating request and response objects
$httpResponseFactory = new \GuzzleHttp\Psr7\HttpFactory;
$gateway = $registry->get('MyCustomGateway'); // or use MyCustomGateway::class

// 4. Process payment
// use user defined client & http factory make it easier for the user to integrate with their existing HTTP client and factory implementations,
// without forcing them to use a specific library or implementation.
$processor = $gateway->process($transaction, $httpResponseFactory, $httpClient);
// 5. Handle transaction result
if ($transaction->getState() === TransactionState::ERROR) {
    // Handle error
    $errorData = $transaction->getError();
    // Log or display error information
} elseif ($transaction->getState() === TransactionState::SUCCESS) {
    // Handle success
    $resultData = $transaction->getTransactionResultData();
} else {
    // Handle pending or other states
}
```

## How to Implement Custom Gateway

Checkout the [DummyGateway](example/DummyGateway) for a complete example of how to implement a custom gateway using the registry and transaction stack.
This is dummy gateway, it can implemented as offline payment method, or as a template for creating new gateway implementations.
