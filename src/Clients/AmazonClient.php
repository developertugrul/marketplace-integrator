<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Clients;

use DeveloperTugrul\MarketplaceIntegrator\Contracts\MarketplaceClientInterface;
use DeveloperTugrul\MarketplaceIntegrator\Models\Product;
use DeveloperTugrul\MarketplaceIntegrator\Models\Order;
use DeveloperTugrul\MarketplaceIntegrator\Models\OrderItem;
use SellingPartnerApi\Seller\SellerConnector;

class AmazonClient implements MarketplaceClientInterface
{
    private SellerConnector $connector;
    private string $marketplaceId;

    public function __construct(SellerConnector $connector, string $marketplaceId = 'A33AVAJ2PDY3EV')
    {
        $this->connector = $connector;
        $this->marketplaceId = $marketplaceId;
    }

    public function getMarketplaceName(): string
    {
        return 'amazon';
    }

    public function getProducts(array $filters = []): \Generator
    {
        $api = $this->connector->catalogItemsV20220401();
        $pageToken = null;

        do {
            try {
                $keywords = $filters['keywords'] ?? ['*'];

                if (isset($filters['identifiers'])) {
                    $response = $api->searchCatalogItems(
                        marketplaceIds: [$this->marketplaceId],
                        identifiers: $filters['identifiers'],
                        identifiersType: $filters['identifiersType'] ?? 'SKU',
                        includedData: ['identifiers', 'images', 'productTypes', 'salesRanks', 'summaries'],
                        pageToken: $pageToken
                    );
                } else {
                    $response = $api->searchCatalogItems(
                        marketplaceIds: [$this->marketplaceId],
                        keywords: $keywords,
                        includedData: ['identifiers', 'images', 'productTypes', 'salesRanks', 'summaries'],
                        pageToken: $pageToken
                    );
                }

                $dto = $response->dto();

                if (empty($dto->items)) {
                    break;
                }

                foreach ($dto->items as $item) {
                    $product = new Product();
                    $product->marketplace = $this->getMarketplaceName();
                    $product->id = $item->asin;
                    
                    $summary = $item->summaries[0] ?? null;
                    if ($summary) {
                        $product->title = $summary->itemName ?? '';
                        $product->brand = $summary->brandName ?? '';
                        $product->category = $summary->browseClassification->displayName ?? '';
                    }

                    $identifiers = $item->identifiers[0]->identifiers ?? [];
                    foreach ($identifiers as $identifier) {
                        if ($identifier->identifierType === 'SKU') {
                            $product->sku = $identifier->identifier;
                        } elseif (in_array($identifier->identifierType, ['EAN', 'UPC'])) {
                            $product->barcode = $identifier->identifier;
                        }
                    }

                    $images = $item->images[0]->images ?? [];
                    foreach ($images as $image) {
                        if (isset($image->link)) {
                            $product->images[] = $image->link;
                        }
                    }

                    $product->price = 0.0;
                    $product->quantity = 0;

                    yield $product;
                }

                $pageToken = $dto->pagination->nextToken ?? null;

            } catch (\Exception $e) {
                break;
            }

        } while ($pageToken !== null);
    }

    public function getOrders(array $filters = []): \Generator
    {
        $api = $this->connector->ordersV0();
        $nextToken = null;

        $createdAfterStr = $filters['createdAfter'] ?? '-7 days';
        $createdAfter = (new \DateTime($createdAfterStr))->format(\DateTime::ATOM);

        do {
            try {
                $response = $api->getOrders(
                    marketplaceIds: [$this->marketplaceId],
                    createdAfter: $createdAfter,
                    nextToken: $nextToken
                );

                $dto = $response->dto();
                $ordersList = $dto->payload->orders ?? [];

                if (empty($ordersList)) {
                    break;
                }

                foreach ($ordersList as $apiOrder) {
                    $order = new Order();
                    $order->marketplace = $this->getMarketplaceName();
                    $order->id = $apiOrder->amazonOrderId;
                    $order->orderNumber = $apiOrder->amazonOrderId;
                    
                    $order->status = $this->mapOrderStatus($apiOrder->orderStatus);
                    $order->totalPrice = isset($apiOrder->orderTotal) ? (float)$apiOrder->orderTotal->amount : 0.0;
                    $order->createdAt = $apiOrder->purchaseDate;
                    
                    if (isset($apiOrder->buyerInfo)) {
                        $order->customerEmail = $apiOrder->buyerInfo->buyerEmail ?? null;
                        $order->customerFirstName = $apiOrder->buyerInfo->buyerName ?? null;
                    }
                    
                    if (isset($apiOrder->shippingAddress)) {
                        $addr = $apiOrder->shippingAddress;
                        $order->shippingAddress = trim(($addr->addressLine1 ?? '') . ' ' . ($addr->addressLine2 ?? '') . ', ' . ($addr->city ?? '') . ' ' . ($addr->stateOrRegion ?? ''));
                    }

                    try {
                        $itemsResponse = $api->getOrderItems($apiOrder->amazonOrderId);
                        $itemsDto = $itemsResponse->dto();
                        $orderItems = $itemsDto->payload->orderItems ?? [];

                        foreach ($orderItems as $apiItem) {
                            $item = new OrderItem();
                            $item->productId = $apiItem->asin;
                            $item->sku = $apiItem->sellerSKU ?? '';
                            $item->title = $apiItem->title ?? '';
                            $item->quantity = (int)($apiItem->quantityOrdered ?? 0);
                            $item->price = isset($apiItem->itemPrice) ? (float)$apiItem->itemPrice->amount : 0.0;
                            
                            $order->items[] = $item;
                        }
                    } catch (\Exception $e) {
                        // Silently continue if fetching items fails
                    }

                    yield $order;
                }

                $nextToken = $dto->payload->nextToken ?? null;

            } catch (\Exception $e) {
                break;
            }

        } while ($nextToken !== null);
    }

    private function mapOrderStatus(string $amazonStatus): string
    {
        $map = [
            'Pending' => Order::STATUS_NEW,
            'Unshipped' => Order::STATUS_APPROVED,
            'PartiallyShipped' => Order::STATUS_SHIPPED,
            'Shipped' => Order::STATUS_SHIPPED,
            'InvoiceUnconfirmed' => Order::STATUS_INVOICED,
            'Delivered' => Order::STATUS_DELIVERED,
            'Canceled' => Order::STATUS_CANCELED,
            'Unfulfillable' => Order::STATUS_RETURNED
        ];

        return $map[$amazonStatus] ?? Order::STATUS_NEW;
    }
}
