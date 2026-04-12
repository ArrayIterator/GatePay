# Contributing to GatePay

First off, thanks for taking the time to contribute! 🎉

The following is a set of guidelines for contributing to GatePay. These are mostly guidelines, not rules. Use your best judgment, and feel free to propose changes to this document in a pull request.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
  - [Reporting Bugs](#reporting-bugs)
  - [Suggesting Enhancements](#suggesting-enhancements)
  - [Pull Requests](#pull-requests)
- [Development Setup](#development-setup)
- [Style Guidelines](#style-guidelines)
- [Testing](#testing)
- [Commit Messages](#commit-messages)

---

## Code of Conduct

This project and everyone participating in it is governed by our commitment to providing a welcoming and inclusive environment. By participating, you are expected to:

- Be respectful and considerate
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

---

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates.

**When reporting a bug, include:**

- **Clear title** - Descriptive summary of the issue
- **Steps to reproduce** - Detailed steps to reproduce the behavior
- **Expected behavior** - What you expected to happen
- **Actual behavior** - What actually happened
- **Environment** - PHP version, OS, relevant package versions
- **Code samples** - Minimal reproducible example if possible

```markdown
### Bug Report Template

**Description:**
A clear description of what the bug is.

**Steps to Reproduce:**
1. Go to '...'
2. Click on '...'
3. See error

**Expected Behavior:**
What you expected to happen.

**Actual Behavior:**
What actually happened.

**Environment:**
- PHP Version: [e.g., 8.2]
- OS: [e.g., Ubuntu 22.04]
- GatePay Version: [e.g., 1.0.0]

**Additional Context:**
Any other context about the problem.
```

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **Use case** - Why is this enhancement needed?
- **Proposed solution** - How should it work?
- **Alternatives considered** - Other approaches you've thought about
- **Additional context** - Any other relevant information

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow the style guidelines** outlined below
3. **Add tests** for any new functionality
4. **Ensure all tests pass** before submitting
5. **Update documentation** if needed
6. **Write a clear PR description** explaining your changes

---

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- Composer

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/GatePay-Core.git
cd GatePay-Core

# Install dependencies
composer install

# Run tests to verify setup
composer test
```

### Available Commands

```bash
# Run tests
composer test
# or
./vendor/bin/phpunit

# Run tests with coverage
./vendor/bin/phpunit

# Run code style check
./vendor/bin/phpcs

# Fix code style automatically
./vendor/bin/phpcbf

# Run static analysis
./vendor/bin/phpstan analyse
```

---

## Style Guidelines

### PHP Code Style

We follow **PSR-12** coding standards with some additional conventions:

- **Strict types** - All PHP files must declare `strict_types=1`
- **Type declarations** - Use parameter and return type declarations
- **DocBlocks** - Document all public methods and complex logic
- **No unused imports** - Remove unused `use` statements

```php
<?php
declare(strict_types=1);

namespace GatePay\Core;

use GatePay\Core\Interfaces\ExampleInterface;

/**
 * Brief description of the class.
 */
class Example implements ExampleInterface
{
    /**
     * Brief description of the method.
     *
     * @param string $param Description of parameter
     * @return bool Description of return value
     */
    public function doSomething(string $param): bool
    {
        // Implementation
        return true;
    }
}
```

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `TransactionProcessor` |
| Methods | camelCase | `processTransaction()` |
| Properties | camelCase | `$transactionId` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRY_COUNT` |
| Interfaces | PascalCase + Interface suffix | `GatewayInterface` |
| Abstract Classes | Abstract prefix + PascalCase | `AbstractGateway` |
| Enums | PascalCase | `TransactionState` |

---

## Testing

### Writing Tests

- Place tests in the `tests/` directory mirroring the `src/` structure
- Use descriptive test method names (without `test` prefix when using `#[Test]` attribute)
- Test both happy paths and edge cases
- Use data providers for testing multiple scenarios

```php
<?php
declare(strict_types=1);

namespace GatePay\CoreTests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    #[Test]
    public function processTransactionReturnsSuccessForValidInput(): void
    {
        // Arrange
        $processor = new TransactionProcessor();
        
        // Act
        $result = $processor->process($validTransaction);
        
        // Assert
        $this->assertTrue($result->isSuccess());
    }

    #[Test]
    public function processTransactionThrowsExceptionForInvalidInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        $processor = new TransactionProcessor();
        $processor->process($invalidTransaction);
    }
}
```

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/TransactionTest.php

# Run specific test method
./vendor/bin/phpunit --filter testMethodName

# Run with coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Coverage

- Aim for **80%+ code coverage** for new code
- Critical paths (payment processing, state management) should have **100% coverage**

---

## Commit Messages

We follow the **Conventional Commits** specification:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Types

| Type | Description |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only changes |
| `style` | Code style changes (formatting, etc.) |
| `refactor` | Code refactoring without feature changes |
| `test` | Adding or updating tests |
| `chore` | Maintenance tasks |

### Examples

```bash
# Feature
feat(gateway): add support for PayPal Express Checkout

# Bug fix
fix(transaction): handle null response from gateway

# Documentation
docs(readme): update installation instructions

# Tests
test(parser): add coverage for CDATA handling
```

---

## Questions?

Feel free to open an issue with the `question` label if you have any questions about contributing.

---

**Thank you for contributing to GatePay!** 🙏
