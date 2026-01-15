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
