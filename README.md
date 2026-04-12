# 💳 GatePay

### The Payment Gateway Library That Actually Makes Sense

> **Stop wrestling with payment integrations. Start building.**


[![PHP CI](https://github.com/TrayDigita/Gatepay-Core/actions/workflows/ci.yaml/badge.svg)](https://github.com/TrayDigita/CreditCard/actions/workflows/ci.yaml)
[![codecov](https://codecov.io/gh/TrayDigita/Gatepay-Core/branch/main/graph/badge.svg)](https://codecov.io/gh/TrayDigita/Gatepay-Core)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net)

---

Small Libraries **BIG IMPACT!**

_GatePay_ is not just another payment gateway wrapper.
It's a **deterministic, traceable, and swappable** payment processing framework designed to make your life easier.
No need to read hundreds files of core code to understand how to process a payment. No more 3 AM debugging sessions trying to figure out why your transaction failed.
With GatePay, you get a clear, predictable flow that works the same way for every gateway implementation.

## 😤 The Problem

Every developer who has integrated payment gateways knows the pain:

| 😱 The Reality | 💀 What You Get |
|---------------|-----------------|
| **Setup Hell** | 500+ lines of config before your first transaction |
| **Spaghetti State** | `if-else` nightmare tracking payment status |
| **Debug Despair** | "Where did my transaction fail?" - You, at 3 AM |
| **Copy-Paste Chaos** | Every gateway = rewrite everything from scratch |
| **Documentation Maze** | 47 tabs open, still confused |
| **Ambiguous Everything** | Is it `PAID`, `paid`, `success`, `SUCCESS`, or `1`? |

### 🤯 Learning Curve Hell

```
Day 1: "I'll just integrate Stripe, how hard can it be?"
Day 2: "Reading docs... why is this so complicated? Harder than Stripe's API!"
Day 5: "Why are there 15 webhook event types?"
Day 10: "I need to support PayPal too... (getting headache ....)"
Day 20: "Everything is on fire"
Day 30: "I should have been a farmer"
```

---

## ✨ The Solution

**GatePay** is built on one principle: **Deterministic Payment Processing**

```
Transaction Created → Action Executed → State Changed → Done.
No magic. No surprises. No 3 AM debugging sessions.
```

### 🎯 What We Need To Solve

| Problem | GatePay Solution                                     |
|---------|------------------------------------------------------|
| Complex Setup | 5 lines to process your first payment                |
| Untraceable Flows | Every state change is logged & trackable             |
| Gateway Lock-in | Swap gateways with zero code changes                 |
| State Chaos | Clear enum states: `PENDING`, `SUCCESS`, `ERROR`     |
| Logging Nightmare | PSR-3 logger integration built-in                    |
| HTTP Headaches | PSR-7/PSR-17/PSR-18 compliant - use ANY HTTP client  |
| Ambiguous Responses | Consistent `@attributes` + `@value` format for `XML` |

---

## 🚀 Quick Start

```php
<?php
// That's it. Seriously.

$gateway = $registry->get('PayPal');
$processor = $gateway->process($transaction, $httpFactory, $httpClient);
// Handle result with clean states, no magic strings
// maybe in async process or webhook handler?
match ($transaction->getState()) {
    TransactionState::SUCCESS => handleSuccess($transaction),
    TransactionState::ERROR   => handleError($transaction),
    TransactionState::PENDING => handlePending($transaction),
    TransactionState::BEGIN   => handleProcessing($transaction),
};
```

**No 500-line setup. No config files. No magic strings.**

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      GatewayRegistry                        │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────────────┐ │
│  │ PayPal  │  │ Stripe  │  │ Bank API│  │ YourCustomGW    │ │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────────┬────────┘ │
└───────┼────────────┼────────────┼────────────────┼──────────┘
        │            │            │                │
        └────────────┴─────┬──────┴────────────────┘
                           │
                    ┌──────▼──────┐
                    │ Transaction │ ← Deterministic State Machine
                    │   (Stack)   │
                    └──────┬──────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
   ┌────▼────┐       ┌─────▼─────┐      ┌─────▼─────┐
   │ PENDING │  ───► │  SUCCESS  │  or  │   ERROR   │
   └─────────┘       └───────────┘      └───────────┘
```

---

## 📦 Features

### 🔌 PSR Compliant - Use What You Already Have
- **PSR-7** - HTTP Messages (Request/Response)
- **PSR-17** - HTTP Factories
- **PSR-18** - HTTP Client
- **PSR-3** - Logger

```php
// Guzzle? Sure.
$client = new GuzzleHttp\Client();

// Symfony HTTP Client? No problem.
$client = new Symfony\Component\HttpClient\Psr18Client();

// Your custom client? Go ahead.
$client = new YourAwesomeHttpClient();

// GatePay doesn't care. It just works.
$gateway->process($transaction, $factory, $client);
```

### 🎭 Human-Readable States

```php
// Before (every gateway is different)

if ($response['status'] === 'PAID') { }      // Gateway A
if ($response['state'] === 1) { }            // Gateway B  
if ($response['result'] === 'success') { }   // Gateway C
if ($response['code'] === '00') { }          // Gateway D 🤮

// After (GatePay)
// The Transaction object is the single source of truth for your transaction's state
if ($transaction->getState() === TransactionState::SUCCESS) {
    // TransactionResponseInterface
    // ALL You need here:
    // 1. Transaction: The original transaction object with all your parameters
    // 2. Transaction Status: GatePay\Core\Enum\TransactionState
    // 3. PSR Response: Psr\Http\Message\ResponseInterface -> Your gateway's raw response, YOU NEED IT!!
    $transactionResponse = $transaction->getResponse();
}

// That's it. For ALL gateways.
// Note: If the payment gateway developer follow the standard!

```

### 📋 Built-in Traceability

Every transaction is traceability & centralized in a transaction.

```php
// Transaction Object = Owner of the entire transaction lifecycle
$my_transaction = new Transaction(...);

$processor = $gateway->process($my_transaction, $httpFactory, $httpClient);

$my_transaction->getState(); // PENDING, SUCCESS, ERROR
$processor->getTransaction() === $my_transaction; // True, it's the same object
// Debug like a pro, not like a detective
```

### 🏭 Easy Gateway Creation

```php
// Create a new gateway in minutes, not days
class MyGateway extends AbstractGateway
{
    protected string $name = "MyGateway";
    protected array $actions = [
        GatewayAction::CHARGE->value => ChargeAction::class,
        GatewayAction::REFUND->value => RefundAction::class,
    ];
}

// That's your gateway. Register and use.
$registry->add(new MyGateway());
// and add the alias if you want
$registry->addAlias('MyGW', MyGateway::class);
```

---

## 💡 Full Example

```php
<?php
declare(strict_types=1);

use GatePay\Core\Enum\GatewayAction;
use GatePay\Core\Enum\TransactionState;
use GatePay\Core\GatewayRegistry;
use GatePay\Core\Transaction;
use GatePay\Core\Utils\ReferenceOrderId;

// the registry centralizes all your gateways, you can also add alias for easier access
$registry = new GatewayRegistry();
// .... any gateway registration here,
// you can also register your gateway in a service provider or bootstrap file, it's up to you

// 1️⃣ Generate unique order ID (k-sortable, prefixed) - always 30 characters, perfect for payment systems
$orderIdGen = new ReferenceOrderId('PYMT');
$orderId = $orderIdGen->generate(); 
// → PYMT-019d43d20eb8-6a5a7925dfb5

// 2️⃣ Create transaction with clear parameters
$transaction = new Transaction(
    transactionId: $orderId,
    action: GatewayAction::CHARGE,
    parameters: [
        'amount' => 100000,
        'currency' => 'IDR',
        'card_number' => '4111111111111111',
    ]
);

// 3️⃣ Setup (use any PSR-18 client & PSR-17 factory)
$httpClient = new GuzzleHttp\Client();
$httpFactory = new GuzzleHttp\Psr7\HttpFactory();

// 4️⃣ Process - one line, any gateway
$gateway = $registry->get('MyGateway');
if (!$gateway->hasAction($transaction->getAction())) {
    // maybe log or throw custom exception,
    // but the point is you don't need to check for null or catch exception just to check if the gateway support the action,
    // you can just do logic here and let the gateway handle it, it's more clean and less error prone
    return;
}

$processor = $gateway->process($transaction, $httpFactory, $httpClient);

// 5️⃣ Handle result - clean, predictable states
match ($transaction->getState()) {
    TransactionState::SUCCESS => fn() => saveSuccess($transaction->getTransactionResultData()),
    TransactionState::ERROR   => fn() => logError($transaction->getError()),
    TransactionState::PENDING => fn() => queueForPolling($transaction),
    TransactionState::BEGIN   => fn() => handleProcessing($transaction),
};
```

---

## 🛠️ Utilities

### Reference Order ID Generator
Optimized for payment systems - k-sortable, prefixed, sufficient entropy.

```php
$gen = new ReferenceOrderId('INVX');
$id = $gen->generate(); // INVX-019d43d20eb8-6a5a7925dfb5
```

📖 [Documentation](src/Utils/README.md#reference-generator)

### XML Parser
Consistent parsing across SimpleXML, LibXML, and Pure PHP.

```php
$result = XMLParserArray::parse($xmlString);
// Same output format, regardless of available extensions
```

📖 [Documentation](src/Utils/README.md#xml-parser)

---

## 🎓 Learning Path

| Step | Time | What You Learn |
|------|------|----------------|
| 1 | 5 min | Process your first transaction |
| 2 | 15 min | Understand Transaction States |
| 3 | 30 min | Create a custom Gateway |
| 4 | 1 hour | Master Actions & Processors |

**Prerequisites:** Basic understanding of PSR-7, PSR-17, PSR-18

---

## 📂 Resources

- 📘 [DummyGateway Example](example/DummyGateway) - Complete gateway implementation reference
- 📗 [Transaction Stack](src/TransactionStack.php) - State management internals
- 📙 [Utilities Documentation](src/Utils/README.md) - Helper tools

---

## 🤝 Philosophy

```
"Make Payment Integration Boring Again."
```

Payment processing should be:
- ✅ **Predictable** - Same input = Same output
- ✅ **Traceable** - Know exactly what happened and when
- ✅ **Swappable** - Change gateways without rewriting code
- ✅ **Debuggable** - Find issues in minutes, not hours
- ✅ **Learnable** - Understand in a day, master in a week

---

## 📜 License

[MIT License](LICENSE) - Use it, modify it, ship it.

---

<!--suppress HtmlDeprecatedAttribute -->
<p align="center" style="text-align: center">
  <b>Stop fighting your payment gateway. Start shipping features.</b>
  <br><br>
  Built with ❤️ for developers who have better things to do than debug payment integrations.
</p>
