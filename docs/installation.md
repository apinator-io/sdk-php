# Installation

## Composer

```bash
composer require apinator/apinator-php
```

## Requirements

- PHP 8.1 or higher
- No external dependencies — uses only PHP stdlib (`hash_hmac`, `json_encode`, `file_get_contents`)

## Manual Installation

If you're not using Composer, download the source and include the autoloader:

```php
require_once __DIR__ . '/path/to/apinator-php/src/Auth.php';
require_once __DIR__ . '/path/to/apinator-php/src/Webhook.php';
require_once __DIR__ . '/path/to/apinator-php/src/Apinator.php';
require_once __DIR__ . '/path/to/apinator-php/src/Errors/RealtimeException.php';
// Note: Classes are in the Apinator\ namespace (e.g., Apinator\Apinator, Apinator\Auth)
require_once __DIR__ . '/path/to/apinator-php/src/Errors/ApiException.php';
require_once __DIR__ . '/path/to/apinator-php/src/Errors/AuthenticationException.php';
require_once __DIR__ . '/path/to/apinator-php/src/Errors/ValidationException.php';
```

Composer autoloading is strongly recommended.
