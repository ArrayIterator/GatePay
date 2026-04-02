# GatePay - The Payment Gateway Registry

Payment Gateway Registry based on **`Deterministic`** concept,
where the transaction flow clearly serialized and deterministic,
allowing for better error handling, retry mechanisms, preventing and easier debugging.

Using PSR-7 and PSR-18 for HTTP client and message interfaces,
ensuring interoperability and flexibility in choosing HTTP client implementations.


## Description

For Product Reference Order Generation [**ReferenceOrderId**](src/Utils/ReferenceOrderId.php)

Verdict: ReferenceOrderId suitable for payment gateway rather than uuid-v7

- [x] Length optimization (30 vs 36 chars)
- [x] Product identification via prefix
- [x] Still k-sortable like UUIDv7
- [x] Sufficient entropy (96 bits)

Example usage:


```php
<?php
declare(strict_types=1);

use ArrayIterator\GatePay\Utils\ReferenceOrderId;

$prefix = "INVC" 
$orderIdGen = new ReferenceOrderId($prefix);

// Generate random
// INVC-019d43d20eb8-6a5a7925dfb5
$referenceId = $orderIdGen->generate();

```

## Integration

Link: https://github.com/ArrayIterator/credit-card

Note: Default transaction stack [TransactionStack.php](src/TransactionStack.php)

```php
<?php
declare(strict_types=1);

use ArrayIterator\GatePay\Enum\TransactionState;use ArrayIterator\GatePay\GatewayRegistry;use ArrayIterator\GatePay\Transaction;
use ArrayIterator\GatePay\Utils\ReferenceOrderId;
use ArrayIterator\CreditCard\CreditCard;

// 1. Generate Order ID
$orderIdGen = new ReferenceOrderId('PYMT');
$orderId = $orderIdGen->generate(); // PYMT-019d43d20eb8-6a5a7925dfb5

$pan = '4111111111111111';
// 2. Validate Credit Card before process
$card = new CreditCard();
$cardType = $card->guess($pan); 

$registry = new GatewayRegistry();
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
$gateway = $registry->get('PayPal');
// 4. Process payment
$processor = $gateway->process($transaction, $httpClient);
// 5. Handle transaction result
if ($transaction->getState() === TransactionState::ERROR) {
    // Handle error
    $errorData = $transaction->getError();
    // Log or display error information
} elseif ($transaction->getState() === TransactionState::SUCCESS) {
    // Handle success
    $resultData = $transaction->getTransactionResultData()l
} else {
    // Handle pending or other states
}
```
