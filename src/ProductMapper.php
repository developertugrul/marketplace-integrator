<?php

namespace DeveloperTugrul\MarketplaceIntegrator;

use DeveloperTugrul\MarketplaceIntegrator\Contracts\ProductMapperInterface;
use DeveloperTugrul\MarketplaceIntegrator\Models\Product;

class ProductMapper implements ProductMapperInterface
{
    private array $mappingBySku = [];
    private array $mappingByBarcode = [];

    public function addInternalProduct($internalId, string $sku, string $barcode = ''): void
    {
        if (!empty($sku)) {
            $this->mappingBySku[$sku] = $internalId;
        }
        if (!empty($barcode)) {
            $this->mappingByBarcode[$barcode] = $internalId;
        }
    }

    public function mapProduct(Product $marketplaceProduct)
    {
        if (!empty($marketplaceProduct->barcode) && isset($this->mappingByBarcode[$marketplaceProduct->barcode])) {
            return $this->mappingByBarcode[$marketplaceProduct->barcode];
        }

        if (!empty($marketplaceProduct->sku) && isset($this->mappingBySku[$marketplaceProduct->sku])) {
            return $this->mappingBySku[$marketplaceProduct->sku];
        }

        return null; // No match found
    }
}
