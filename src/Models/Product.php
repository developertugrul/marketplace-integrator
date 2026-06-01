<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Models;

class Product
{
    public string $id;
    public string $sku;
    public ?string $barcode;
    public string $title;
    public float $price;
    public int $quantity;
    public string $marketplace;
    public ?string $brand;
    public ?string $category;
    public ?string $description;
    public array $images;
    public array $attributes;

    public function __construct(
        string $id,
        string $sku,
        ?string $barcode,
        string $title,
        float $price,
        int $quantity,
        string $marketplace,
        ?string $brand = null,
        ?string $category = null,
        ?string $description = null,
        array $images = [],
        array $attributes = []
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->barcode = $barcode;
        $this->title = $title;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->marketplace = $marketplace;
        $this->brand = $brand;
        $this->category = $category;
        $this->description = $description;
        $this->images = $images;
        $this->attributes = $attributes;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'title' => $this->title,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'marketplace' => $this->marketplace,
            'brand' => $this->brand,
            'category' => $this->category,
            'description' => $this->description,
            'images' => $this->images,
            'attributes' => $this->attributes,
        ];
    }
}
