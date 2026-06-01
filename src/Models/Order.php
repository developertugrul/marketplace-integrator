<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Models;

class Order
{
    const STATUS_NEW = 'new';
    const STATUS_INVOICED = 'invoiced';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_UNKNOWN = 'unknown';

    public string $id;
    public string $orderNumber;
    public string $status;
    public float $totalPrice;
    public string $marketplace;
    public \DateTimeInterface $createdAt;
    
    public ?string $customerFirstName;
    public ?string $customerLastName;
    public ?string $customerEmail;
    public ?string $customerPhone;
    
    public ?string $shippingAddress;
    public ?string $billingAddress;
    public ?string $cargoProvider;
    public ?string $cargoTrackingNumber;

    /** @var OrderItem[] */
    public array $items = [];

    public function __construct(
        string $id,
        string $orderNumber,
        string $status,
        float $totalPrice,
        string $marketplace,
        \DateTimeInterface $createdAt,
        ?string $customerFirstName = null,
        ?string $customerLastName = null,
        ?string $customerEmail = null,
        ?string $customerPhone = null,
        ?string $shippingAddress = null,
        ?string $billingAddress = null,
        ?string $cargoProvider = null,
        ?string $cargoTrackingNumber = null
    ) {
        $this->id = $id;
        $this->orderNumber = $orderNumber;
        $this->status = $status;
        $this->totalPrice = $totalPrice;
        $this->marketplace = $marketplace;
        $this->createdAt = $createdAt;
        
        $this->customerFirstName = $customerFirstName;
        $this->customerLastName = $customerLastName;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->cargoProvider = $cargoProvider;
        $this->cargoTrackingNumber = $cargoTrackingNumber;
    }

    public function addItem(OrderItem $item): void
    {
        $this->items[] = $item;
    }
}
