<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Clients;

use DeveloperTugrul\MarketplaceIntegrator\Contracts\MarketplaceClientInterface;
use DeveloperTugrul\MarketplaceIntegrator\Models\Product;
use DeveloperTugrul\MarketplaceIntegrator\Models\Order;
use DeveloperTugrul\MarketplaceIntegrator\Models\OrderItem;
use GuzzleHttp\Client;

class TrendyolClient implements MarketplaceClientInterface
{
    private Client $httpClient;
    private string $supplierId;
    private string $apiKey;
    private string $apiSecret;

    public function __construct(string $supplierId, string $apiKey, string $apiSecret, array $config = [])
    {
        $this->supplierId = $supplierId;
        $this->apiKey = base64_encode($apiKey . ':' . $apiSecret);
        
        $defaultConfig = [
            'base_uri' => 'https://api.trendyol.com/sapigw/',
            'headers' => [
                'Authorization' => 'Basic ' . $this->apiKey,
                'User-Agent' => $supplierId . ' - DeveloperTugrul Integrator',
                'Content-Type' => 'application/json',
            ]
        ];

        $this->httpClient = new Client(array_merge($defaultConfig, $config));
    }

    public function getMarketplaceName(): string
    {
        return 'trendyol';
    }

    public function getProducts(array $filters = []): \Generator
    {
        $page = $filters['page'] ?? 0;
        $size = $filters['size'] ?? 100;
        
        do {
            $query = array_merge($filters, ['page' => $page, 'size' => $size]);
            $response = $this->httpClient->get("suppliers/{$this->supplierId}/products", [
                'query' => $query
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            $items = $data['content'] ?? [];

            foreach ($items as $item) {
                yield $this->mapProduct($item);
            }

            $page++;
        } while (count($items) === $size);
    }

    public function getProduct(string $id): ?Product
    {
        // Trendyol can filter products by barcode or stockCode. 
        // If ID is internal product ID, we might need a specific endpoint or use filtering.
        // Assuming $id here is stockCode (SKU) as per Trendyol structure
        $response = $this->httpClient->get("suppliers/{$this->supplierId}/products", [
            'query' => ['barcode' => $id] // Adjust based on your primary identifier (barcode/stockCode)
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        if (!empty($data['content'])) {
            return $this->mapProduct($data['content'][0]);
        }
        
        return null;
    }

    public function getOrders(array $filters = []): \Generator
    {
        $page = $filters['page'] ?? 0;
        $size = $filters['size'] ?? 50;

        do {
            $query = array_merge($filters, ['page' => $page, 'size' => $size]);
            $response = $this->httpClient->get("suppliers/{$this->supplierId}/orders", [
                'query' => $query
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $items = $data['content'] ?? [];

            foreach ($items as $item) {
                yield $this->mapOrder($item);
            }

            $page++;
        } while (count($items) === $size);
    }

    public function getOrder(string $orderNumber): ?Order
    {
        $response = $this->httpClient->get("suppliers/{$this->supplierId}/orders", [
            'query' => ['orderNumber' => $orderNumber]
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        if (!empty($data['content'])) {
            return $this->mapOrder($data['content'][0]);
        }
        
        return null;
    }

    private function mapProduct(array $item): Product
    {
        $images = array_map(function ($img) {
            return $img['url'] ?? '';
        }, $item['images'] ?? []);

        return new Product(
            (string)($item['id'] ?? ''),
            $item['stockCode'] ?? '',
            $item['barcode'] ?? null,
            $item['title'] ?? '',
            (float)($item['salePrice'] ?? 0.0),
            (int)($item['quantity'] ?? 0),
            $this->getMarketplaceName(),
            $item['brand'] ?? null,
            $item['categoryName'] ?? null,
            $item['description'] ?? null,
            $images,
            $item['attributes'] ?? []
        );
    }

    private function mapOrder(array $item): Order
    {
        // Trendyol Status Mapping
        $statusMapping = [
            'Created' => Order::STATUS_NEW,
            'Picking' => Order::STATUS_INVOICED,
            'Invoiced' => Order::STATUS_INVOICED,
            'Shipped' => Order::STATUS_SHIPPED,
            'Delivered' => Order::STATUS_DELIVERED,
            'Cancelled' => Order::STATUS_CANCELLED,
            'UnDelivered' => Order::STATUS_CANCELLED,
        ];
        
        $rawStatus = $item['shipmentPackageStatus'] ?? ($item['status'] ?? 'Created');
        $mappedStatus = $statusMapping[$rawStatus] ?? Order::STATUS_UNKNOWN;

        $order = new Order(
            (string)($item['id'] ?? ''),
            $item['orderNumber'] ?? '',
            $mappedStatus,
            (float)($item['totalPrice'] ?? 0.0),
            $this->getMarketplaceName(),
            new \DateTime('@' . (($item['orderDate'] ?? 0) / 1000)),
            $item['customerFirstName'] ?? null,
            $item['customerLastName'] ?? null,
            $item['customerEmail'] ?? null,
            null, // phone typically not provided directly without masking
            $this->formatAddress($item['shipmentAddress'] ?? []),
            $this->formatAddress($item['invoiceAddress'] ?? []),
            $item['cargoProviderName'] ?? null,
            $item['cargoTrackingNumber'] ?? null
        );

        if (isset($item['lines'])) {
            foreach ($item['lines'] as $line) {
                $orderItem = new OrderItem(
                    (string)$line['productId'],
                    $line['sku'] ?? ($line['merchantSku'] ?? ''),
                    $line['productName'] ?? '',
                    (int)($line['quantity'] ?? 1),
                    (float)($line['price'] ?? 0.0)
                );
                $order->addItem($orderItem);
            }
        }

        return $order;
    }

    private function formatAddress(array $addressData): string
    {
        if (empty($addressData)) {
            return '';
        }
        $parts = [
            $addressData['fullAddress'] ?? '',
            $addressData['district'] ?? '',
            $addressData['city'] ?? ''
        ];
        return implode(', ', array_filter($parts));
    }
}
