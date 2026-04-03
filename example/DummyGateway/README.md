# DummyGateway Example

This is a reference implementation demonstrating how to create a custom payment gateway using the GatePay framework. The DummyGateway is designed for **testing and development purposes** - it simulates payment processing without making actual HTTP requests to a remote server.

## Purpose

The DummyGateway serves several purposes:

1. **Learning Reference** - Shows how to properly extend `AbstractGateway` and implement gateway actions
2. **Testing** - Provides a mock gateway for unit and integration tests
3. **Offline Processing** - Demonstrates how to create gateways that don't require remote HTTP calls (e.g., bank transfer, offline payment)
4. **Local SDK Integration** - Shows how to wrap a local SDK/library as a gateway

## Structure

```
DummyGateway/
├── README.md                 # This file
├── DummyGateway.php         # Main gateway class extending AbstractGateway
├── LocalClient.php          # PSR-18 compatible local HTTP client (mock)
└── Actions/
    └── TestAction.php       # Example action implementation
```

## Components

### DummyGateway.php

The main gateway class that:
- Extends `AbstractGateway`
- Defines gateway name as `"DummyGateway"`
- Registers supported actions (maps `GatewayAction::TEST` to `TestAction`)
- Overrides `prepareClient()` to use `LocalClient` instead of real HTTP client

```php
class DummyGateway extends AbstractGateway
{
    protected string $name = "DummyGateway";
    protected LocalClient $client;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->client = new LocalClient($responseFactory);
        $this->actions = [
            GatewayAction::TEST->value => TestAction::class
        ];
    }

    protected function prepareClient(
        ClientInterface $client,
        TransactionProcessorInterface $processor,
        ?LoggerInterface $logger = null
    ): ClientInterface {
        // Return local client instead of the provided HTTP client
        return $this->client;
    }
}
```

### LocalClient.php

A PSR-18 `ClientInterface` implementation that:
- Simulates HTTP request/response without network calls
- Validates request parameters (`transaction_id`, `amount`, `currency`)
- Returns JSON responses matching real gateway response format
- Can be used for:
  - Unit testing without mocking
  - Offline payment gateways (bank transfer, cash on delivery)
  - Wrapping local SDKs provided by payment providers

### Actions/TestAction.php

Example action implementation that:
- Extends `AbstractGatewayAction`
- Implements `GatewayAction::TEST` action type
- Validates transaction parameters (`test_param` must equal `'test_value'`)
- Creates HTTP request with transaction data as query parameters

## Usage

### 1. Bootstrap

```php
<?php
require __DIR__ . '/bootstrap.php';

use GatePay\Example\DummyGateway\DummyGateway;
use Nyholm\Psr7\Factory\Psr17Factory;
```

### 2. Create Gateway Instance

```php
$factory = new Psr17Factory();
$gateway = new DummyGateway($factory);
```

### 3. Create Transaction

```php
use GatePay\Core\Transaction;
use GatePay\Core\Enum\GatewayAction;

$transaction = new Transaction(
    transactionId: 'TXN-001',
    action: GatewayAction::TEST,
    parameters: [
        'test_param' => 'test_value',  // Required by TestAction
        'amount' => 100000,
        'currency' => 'IDR',
    ]
);
```

### 4. Process Transaction

```php
use GatePay\Core\Enum\TransactionState;

$processor = $gateway->process($transaction, $httpClient);

if ($transaction->getState() === TransactionState::SUCCESS) {
    $result = $transaction->getTransactionResultData();
    // Handle success
} elseif ($transaction->getState() === TransactionState::ERROR) {
    $error = $transaction->getError();
    // Handle error
}
```

## Creating Your Own Gateway

Use this example as a template:

1. **Create Gateway Class** - Extend `AbstractGateway`
   ```php
   class MyGateway extends AbstractGateway
   {
       protected string $name = "MyGateway";
       protected string $productionUrl = "https://api.example.com";
       protected string $sandboxUrl = "https://sandbox.example.com";
       
       public function __construct()
       {
           $this->actions = [
               GatewayAction::CHARGE->value => ChargeAction::class,
               GatewayAction::REFUND->value => RefundAction::class,
           ];
       }
   }
   ```

2. **Create Action Classes** - Extend `AbstractGatewayAction`
   ```php
   class ChargeAction extends AbstractGatewayAction
   {
       public function getAction(): GatewayAction
       {
           return GatewayAction::CHARGE;
       }
       
       public function createRequest(...): RequestInterface
       {
           // Build HTTP request for charge endpoint
       }
   }
   ```

3. **Register Gateway** (optional)
   ```php
   $registry = new GatewayRegistry();
   $registry->register($myGateway);
   ```

## LocalClient Response Format

### Success Response (HTTP 200)
```json
{
    "status": "success",
    "message": "This is a dummy response from LocalClient.",
    "data": {
        "transaction_id": "TXN-001",
        "amount": 100000,
        "currency": "IDR"
    }
}
```

### Error Response (HTTP 400)
```json
{
    "status": "error",
    "message": "Missing or invalid transaction_id in the request."
}
```

## Key Concepts Demonstrated

| Concept | File | Description |
|---------|------|-------------|
| Gateway Extension | `DummyGateway.php` | How to extend `AbstractGateway` |
| Action Registration | `DummyGateway.php` | Lazy-load actions using class names |
| Client Override | `DummyGateway.php` | Replace HTTP client in `prepareClient()` |
| Mock Client | `LocalClient.php` | PSR-18 compliant local processing |
| Action Implementation | `TestAction.php` | Validate params & create requests |
| Parameter Validation | `TestAction.php` | Use `isProcessable()` for validation |

