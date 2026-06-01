<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Contracts;

use DeveloperTugrul\MarketplaceIntegrator\Models\Order;
use DeveloperTugrul\MarketplaceIntegrator\Models\Product;

interface MarketplaceClientInterface
{

    /**
     * Get the name of the marketplace (e.g., 'amazon', 'trendyol').
     *
     * @return string
     */
    public function getMarketplaceName(): string;
}
