# PHPUnit Run Failed

[![Packagist Version](https://img.shields.io/packagist/v/mracos/phpunit-run-failed)](https://packagist.org/packages/mracos/phpunit-run-failed)
[![PHP Version](https://img.shields.io/packagist/dependency-v/mracos/phpunit-run-failed/php)](https://packagist.org/packages/mracos/phpunit-run-failed)
[![Downloads](https://img.shields.io/packagist/dt/mracos/phpunit-run-failed)](https://packagist.org/packages/mracos/phpunit-run-failed)
[![License](https://img.shields.io/packagist/l/mracos/phpunit-run-failed)](LICENSE)

PHPUnit extension to re-run only failed tests.

## Installation

```bash
composer require --dev mracos/phpunit-run-failed
```

## Usage

### 1. Add Extension to PHPUnit Configuration

Add the extension to your `phpunit.xml`:

```xml
<phpunit>
    <!-- your existing configuration -->
    <extensions>
        <bootstrap class="PhpUnitRunFailed\FailedTestRerunnerExtension" />
    </extensions>
</phpunit>
```

### 2. Ignore where we store the last failed tests

Failed test information is stored in `.phpunit-failed-tests.json` in your project root. Add this to your `.gitignore`:

```gitignore
.phpunit-failed-tests.json
```

### 3. Run Tests Normally

```bash
vendor/bin/phpunit
```

The extension automatically tracks any failed tests.

### 3. Re-run Only Failed Tests

Use PHPUnit's native `--testsuite` option:

```bash
vendor/bin/phpunit --testsuite=failed
```

## Compatibility

- **PHP**: 8.0+
- **PHPUnit**: 10.0+, 11.0+
- **Integration**: Works with existing PHPUnit configurations and other extensions

## Test Project

This repository includes a `test-project/` directory that serves as an integration test for the extension. It demonstrates how the extension works in a real PHPUnit environment.

### What is the Test Project?

The `test-project/` is a minimal PHP project that:
- Has its own `composer.json` with PHPUnit as a dependency
- Includes sample test files with intentionally failing tests
- Demonstrates the full extension workflow in isolation
- Serves as a reference implementation for integration

### How to Use the Test Project

1. **Navigate to the test project**:
   ```bash
   cd test-project
   ```

2. **Install dependencies** (automatically done):
   ```bash
   composer install
   ```

3. **Run the initial test suite** (creates failures):
   ```bash
   vendor/bin/phpunit
   ```
   This will run the tests with several intentional failures and errors:

4. **Test the --testsuite=failed functionality**:
   ```bash
   vendor/bin/phpunit --testsuite=failed
   ```

Should re-run only the failing tests.

5. Uncomment what makes the tests passes.
Should see `No recorded failed tests found. Running empty test suite.`
