<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Contracts;

use DeveloperTugrul\MarketplaceIntegrator\Models\Product;

interface ProductMapperInterface
{
    /**
     * Map a marketplace product to an internal system product identifier.
     *
     * @param Product $marketplaceProduct
     * @return string|int|null Internal product ID or null if not matched.
     */
    public function mapProduct(Product $marketplaceProduct);

    /**
     * Add or update an internal product to the mapping logic.
     *
     * @param string|int $internalId
     * @param string $sku
     * @param string $barcode
     * @return void
     */
    public function addInternalProduct($internalId, string $sku, string $barcode): void;
}
