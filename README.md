# Tapbuy Forter Integration for Magento 2

This module integrates Forter fraud prevention into Magento 2 for Tapbuy checkout.

## Requirements

- Magento 2.4.x
- PHP 8.1+
- `tapbuy/magento2-redirect-tracking` module

## Installation

```bash
composer require tapbuy/magento2-forter
bin/magento module:enable Tapbuy_Forter
bin/magento setup:upgrade
bin/magento cache:flush
```

## Structure

- `Api/` - API interfaces
- `Exception/` - Custom exceptions
- `Model/` - Business logic models
- `Observer/` - Event observers
- `Plugin/` - Magento plugins
- `etc/` - Module configuration

## Related Modules

- [tapbuy/magento2-forter-adyen](../forter-adyen) - Forter integration with Adyen payment gateway

## Development

### Running Tests

Tests run inside a Docker container that replicates the CI environment (PHP 8.3, Magento 2.4.7-p5). Docker must be running.

**Prerequisites:** clone the following sibling repository next to this one:

```bash
# From the parent directory
git clone git@github.com:tapbuy/magento-redirect-plugin.git redirect-tracking
```

**First-time setup:**

```bash
cp auth.json.dist auth.json
# Fill in your repo.magento.com public/private keys in auth.json
```

**Run all unit tests:**

```bash
make test
```

On the first run, the Docker image is built and Magento is installed into a named volume (`tapbuy-magento-2.4.7-p5-php83`). Subsequent runs reuse the cached volume and are fast.

> Do not use `composer test` — it runs PHPUnit without the Magento bootstrap and will fail or produce misleading results.
