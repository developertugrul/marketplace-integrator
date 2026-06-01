# Marketplace Integrator Pro

![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-blue)
![Laravel Collections](https://img.shields.io/badge/laravel--collections-supported-red)
![Version](https://img.shields.io/badge/version-v1.0.0-success)

A highly optimized, memory-efficient PHP library for pulling, filtering, matching, and copying products and orders from **Trendyol** and **Amazon**.

Designed specifically to handle extremely large product catalogs without memory exhaustion by utilizing PHP Generators (`yield`).

## Features
- **High Performance:** Uses PHP Generators instead of massive arrays. Capable of looping through 100,000+ products with flat memory usage.
- **Unified Models:** Normalizes completely different API structures (Amazon SP-API and Trendyol Supplier API) into a unified `Product` and `Order` model.
- **Standardized Statuses:** Order statuses like `Picking`, `Unshipped`, or `Pending` are all normalized to standard constants like `Order::STATUS_INVOICED`.
- **Fast Product Copy:** Includes a professional column mapper that takes a marketplace product and translates it directly into your database schema using dot-notation.

## Installation

```bash
composer require developertugrul/marketplace-integrator
```

## Documentation

For full, interactive documentation with code examples, open `docs/index.html` in your browser.

## Quick Start: Column Mapping (Fast Copy)

Easily translate a marketplace product into your Laravel/Eloquent model format:

```php
use DeveloperTugrul\MarketplaceIntegrator\MarketplaceManager;

$manager = new MarketplaceManager($mapper);
// Add clients...

$dbColumnMap = [
    'db_title' => 'title',
    'db_price_col' => 'price',
    'stock_amount' => 'quantity',
    'product_sku' => 'sku',
    'brand_name' => 'brand',
    'primary_image' => 'images.0', // Supports dot notation!
];

$insertData = $manager->copyProductToDatabaseFormat('trendyol', '8691234567890', $dbColumnMap);

// Insert into your database
// Product::create($insertData);
```

## Disclaimer

This library focuses entirely on **Read, Filter, List, and Map** operations. It does not contain endpoints for creating or updating products on the marketplaces.

## License

MIT
