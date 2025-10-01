# PHPUnit Run Failed

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

