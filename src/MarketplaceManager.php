<?php

namespace DeveloperTugrul\MarketplaceIntegrator;

use DeveloperTugrul\MarketplaceIntegrator\Contracts\MarketplaceClientInterface;
use DeveloperTugrul\MarketplaceIntegrator\Contracts\ProductMapperInterface;
use DeveloperTugrul\MarketplaceIntegrator\Models\Order;
use DeveloperTugrul\MarketplaceIntegrator\Models\Product;
use Illuminate\Support\Collection;

class MarketplaceManager
{
    /** @var MarketplaceClientInterface[] */
    private array $clients = [];
    
    private ProductMapperInterface $mapper;

    public function __construct(ProductMapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    public function addClient(MarketplaceClientInterface $client): void
    {
        $this->clients[$client->getMarketplaceName()] = $client;
    }

    public function getClient(string $marketplace): ?MarketplaceClientInterface
    {
        return $this->clients[$marketplace] ?? null;
    }

    /**
     * Fetch products from all connected marketplaces using Generators.
     * 
     * @param array $filters Filters to apply.
     * @return \Generator
     */
    public function getMappedProducts(array $filters = []): \Generator
    {
        foreach ($this->clients as $marketplace => $client) {
            foreach ($client->getProducts($filters) as $product) {
                $internalId = $this->mapper->mapProduct($product);
                yield [
                    'marketplace' => $marketplace,
                    'marketplace_product' => $product,
                    'internal_id' => $internalId,
                    'is_matched' => $internalId !== null
                ];
            }
        }
    }

    /**
     * Fetch orders from all connected marketplaces and map their items.
     *
     * @param array $filters Filters to apply.
     * @return \Generator<Order>
     */
    public function getMappedOrders(array $filters = []): \Generator
    {
        foreach ($this->clients as $marketplace => $client) {
            foreach ($client->getOrders($filters) as $order) {
                foreach ($order->items as $item) {
                    // Create a dummy product to reuse mapping logic based on SKU
                    $dummyProduct = new Product($item->productId, $item->sku, null, $item->title, $item->price, $item->quantity, $marketplace);
                    $item->internalProductId = $this->mapper->mapProduct($dummyProduct);
                }
                yield $order;
            }
        }
    }

    /**
     * Copies a product from a specific marketplace and formats it according to database columns.
     * 
     * @param string $marketplace The marketplace name (e.g. 'trendyol', 'amazon').
     * @param string $productId The marketplace product ID or SKU.
     * @param array $columnMap Mapping array: ['db_column_name' => 'product_property_name']
     * @return array|null The mapped array ready for database insertion, or null if not found.
     */
    public function copyProductToDatabaseFormat(string $marketplace, string $productId, array $columnMap): ?array
    {
        $client = $this->getClient($marketplace);
        if (!$client) {
            throw new \InvalidArgumentException("Client for marketplace '{$marketplace}' not found.");
        }

        $product = $client->getProduct($productId);
        if (!$product) {
            return null;
        }

        $productData = collect($product->toArray());
        $formattedData = [];

        foreach ($columnMap as $dbColumn => $productProperty) {
            // Support dot notation if needed using Laravel Collection's data_get
            $formattedData[$dbColumn] = data_get($productData, $productProperty);
        }

        return $formattedData;
    }
}
