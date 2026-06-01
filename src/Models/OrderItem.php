<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Models;

class OrderItem
{
    public string $productId;
    public string $sku;
    public string $title;
    public int $quantity;
    public float $price;
    
    /** @var mixed Internal mapped Product ID */
    public $internalProductId = null;

    public function __construct(
        string $productId,
        string $sku,
        string $title,
        int $quantity,
        float $price
    ) {
        $this->productId = $productId;
        $this->sku = $sku;
        $this->title = $title;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->internalProductId = null;
    }
}
