# Laravel Raraxuan

Laravel SDK for calling the Raraxuan AI API from Laravel applications.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13

## Installation

```bash
composer require latitudeinnovation/laravel-raraxuan
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=raraxuan-config
```

## Configuration

Add these values to your Laravel project's `.env` file:

```dotenv
RARAXUAN_API_URL=https://ai.raraxuan.com/api
RARAXUAN_API_KEY=rx_live_xxxxxxxxx
RARAXUAN_TIMEOUT=60
```

The published config file is `config/raraxuan.php`:

```php
return [
    'base_url' => env('RARAXUAN_API_URL', 'https://ai.raraxuan.com/api'),
    'api_key' => env('RARAXUAN_API_KEY'),
    'timeout' => env('RARAXUAN_TIMEOUT', 60),
];
```

## Usage

Call an agent through the facade:

```php
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

$response = Raraxuan::agent('seo-writer', [
    'topic' => 'Laravel hosting',
]);
```

Or call the generic run endpoint:

```php
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

$response = Raraxuan::run([
    'agent' => 'seo-writer',
    'input' => [
        'topic' => 'Laravel hosting',
    ],
]);
```

## Error Handling

The SDK throws its own exceptions for missing or invalid local configuration:

- `LatitudeInnovation\Raraxuan\Exceptions\MissingApiKeyException`
- `LatitudeInnovation\Raraxuan\Exceptions\InvalidConfigurationException`

API request failures use Laravel's HTTP client and call `throw()` on failed responses. Catch `Illuminate\Http\Client\RequestException` when you need to handle API errors.

```php
use Illuminate\Http\Client\RequestException;
use LatitudeInnovation\Raraxuan\Exceptions\RaraxuanException;
use LatitudeInnovation\Raraxuan\Facades\Raraxuan;

try {
    $response = Raraxuan::agent('seo-writer', [
        'topic' => 'Laravel hosting',
    ]);
} catch (RequestException $exception) {
    report($exception);

    $status = $exception->response->status();
    $error = $exception->response->json();
} catch (RaraxuanException $exception) {
    report($exception);
}
```

## Testing

After installing development dependencies, run:

```bash
composer test
```

## Local Development

Before publishing to Packagist, require this package from a local Laravel project:

```bash
composer config repositories.raraxuan path ../laravel-raraxuan
composer require latitudeinnovation/laravel-raraxuan:@dev
```

If Composer cannot detect the package version, add a branch alias or require `dev-main` from a Git checkout.

## Queue Support

Queue and job helpers are not included yet. A future version can add jobs for long-running agent calls while keeping this client as the synchronous API layer.
