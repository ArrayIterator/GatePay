# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in GatePay, please report it responsibly.

### How to Report

**Option 1:** Open a [GitHub Security Advisory](../../security/advisories/new) (recommended)

**Option 2:** Create a private issue with `[SECURITY]` prefix in the title

### What to Include

- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

---

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x.x   | ✅ |
| < 1.0   | ❌ |

---

## Security Best Practices

When using GatePay:

```php
// ✅ Use environment variables for credentials
$apiKey = getenv('GATEWAY_API_KEY');

// ✅ Always use HTTPS in production
$gateway->setEndpoint('https://api.gateway.com');

// ✅ Don't log sensitive data
$logger->info('Transaction processed', ['id' => $txnId]);
// NOT: $logger->info('Card: ' . $cardNumber);
// NEVER: $logger->info('KEY: ' . $apiKey);
```

---

## For Contributors

Before submitting code:

- No hardcoded credentials
- Validate user inputs
- No sensitive data in error messages or logs
- Keep dependencies updated

---

Thanks for helping keep GatePay secure! 🙏
