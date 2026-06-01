<?php

namespace DeveloperTugrul\MarketplaceIntegrator\Clients;

use DeveloperTugrul\MarketplaceIntegrator\Contracts\MarketplaceClientInterface;
use DeveloperTugrul\MarketplaceIntegrator\Models\Product;
use DeveloperTugrul\MarketplaceIntegrator\Models\Order;
use DeveloperTugrul\MarketplaceIntegrator\Models\OrderItem;
use GuzzleHttp\Client;

class AmazonClient implements MarketplaceClientInterface
{
    private Client $httpClient;
    private string $sellerId;
    private string $marketplaceId;

    public function __construct(string $sellerId, string $marketplaceId, array $config = [])
    {
        $this->sellerId = $sellerId;
        $this->marketplaceId = $marketplaceId;
        
        $defaultConfig = [
            'base_uri' => 'https://sellingpartnerapi-eu.amazon.com/',
            'headers' => [
                'x-amz-access-token' => $config['access_token'] ?? '',
                'User-Agent' => $sellerId . ' - DeveloperTugrul Integrator',
                'Content-Type' => 'application/json',
            ]
        ];

        $this->httpClient = new Client(array_merge($defaultConfig, $config));
    }

    public function getMarketplaceName(): string
    {
        return 'amazon';
    }

    public function getProducts(array $filters = []): \Generator
    {
        $nextToken = null;
        
        do {
            $query = array_merge($filters, [
                'marketplaceIds' => $this->marketplaceId,
                'sellerId' => $this->sellerId,
                'includedData' => 'identifiers,images,productTypes,salesRanks,summaries'
            ]);
            
            if ($nextToken) {
                $query['pageToken'] = $nextToken;
            }

            // Mocking SP-API Catalog Items API v2022-04-01
            // In a real environment, you'd execute:
            // $response = $this->httpClient->get("catalog/2022-04-01/items", ['query' => $query]);
            // $data = json_decode($response->getBody()->getContents(), true);
            
            // Mock Data Generator
            $data = $this->mockAmazonCatalogResponse($nextToken);
            $items = $data['items'] ?? [];

            foreach ($items as $item) {
                yield $this->mapProduct($item);
            }

            $nextToken = $data['pagination']['nextToken'] ?? null;
        } while ($nextToken);
    }

    public function getProduct(string $asin): ?Product
    {
        // Mocking single item fetch
        // $response = $this->httpClient->get("catalog/2022-04-01/items/{$asin}", ['query' => ['marketplaceIds' => $this->marketplaceId]]);
        $mockItem = [
            'asin' => $asin,
            'summaries' => [['itemName' => 'Amazon Mock Product', 'brand' => 'AmazonBasics']],
            'identifiers' => [['identifiers' => [['identifier' => 'SKU-AMZ-MOCK', 'identifierType' => 'SKU']]]],
            'images' => [['images' => [['link' => 'http://example.com/amz.jpg']]]]
        ];
        return $this->mapProduct($mockItem);
    }

    public function getOrders(array $filters = []): \Generator
    {
        $nextToken = null;

        do {
            $query = array_merge($filters, [
                'MarketplaceIds' => $this->marketplaceId
            ]);
            
            if ($nextToken) {
                $query['NextToken'] = $nextToken;
            }

            // Mocking SP-API Orders API v0
            // $response = $this->httpClient->get("orders/v0/orders", ['query' => $query]);
            $data = $this->mockAmazonOrdersResponse($nextToken);
            $items = $data['payload']['Orders'] ?? [];

            foreach ($items as $item) {
                yield $this->mapOrder($item);
            }

            $nextToken = $data['payload']['NextToken'] ?? null;
        } while ($nextToken);
    }

    public function getOrder(string $orderNumber): ?Order
    {
        // Mocking single order fetch
        // $response = $this->httpClient->get("orders/v0/orders/{$orderNumber}");
        $mockOrder = [
            'AmazonOrderId' => $orderNumber,
            'OrderStatus' => 'Shipped',
            'OrderTotal' => ['Amount' => '150.00'],
            'PurchaseDate' => '2026-06-01T10:00:00Z',
            'BuyerInfo' => ['BuyerEmail' => 'buyer@amazon.com', 'BuyerName' => 'John Doe'],
            'ShippingAddress' => ['AddressLine1' => '123 Amazon St', 'City' => 'Seattle']
        ];
        return $this->mapOrder($mockOrder);
    }

    private function mapProduct(array $item): Product
    {
        $summary = $item['summaries'][0] ?? [];
        $identifier = $item['identifiers'][0]['identifiers'][0] ?? [];
        $sku = $identifier['identifierType'] === 'SKU' ? $identifier['identifier'] : $item['asin'];
        
        $images = [];
        if (isset($item['images'][0]['images'])) {
            foreach ($item['images'][0]['images'] as $img) {
                $images[] = $img['link'] ?? '';
            }
        }

        return new Product(
            $item['asin'] ?? '',
            $sku,
            null, // EAN/UPC logic requires deep parsing in SP-API
            $summary['itemName'] ?? 'Unknown',
            0.0, // Pricing requires separate pricing API call in SP-API
            0, // Quantity requires separate inventory API call in SP-API
            $this->getMarketplaceName(),
            $summary['brand'] ?? null,
            null,
            null,
            $images
        );
    }

    private function mapOrder(array $item): Order
    {
        $statusMapping = [
            'Pending' => Order::STATUS_NEW,
            'Unshipped' => Order::STATUS_INVOICED,
            'PartiallyShipped' => Order::STATUS_SHIPPED,
            'Shipped' => Order::STATUS_SHIPPED,
            'Canceled' => Order::STATUS_CANCELLED,
            'Unfulfillable' => Order::STATUS_CANCELLED,
        ];
        
        $rawStatus = $item['OrderStatus'] ?? 'Pending';
        $mappedStatus = $statusMapping[$rawStatus] ?? Order::STATUS_UNKNOWN;

        $address = $item['ShippingAddress'] ?? [];
        $formattedAddress = implode(', ', array_filter([$address['AddressLine1'] ?? '', $address['City'] ?? '']));

        $order = new Order(
            $item['AmazonOrderId'] ?? '',
            $item['AmazonOrderId'] ?? '',
            $mappedStatus,
            (float)($item['OrderTotal']['Amount'] ?? 0.0),
            $this->getMarketplaceName(),
            new \DateTime($item['PurchaseDate'] ?? 'now'),
            $item['BuyerInfo']['BuyerName'] ?? null,
            null,
            $item['BuyerInfo']['BuyerEmail'] ?? null,
            null,
            $formattedAddress,
            $formattedAddress
        );

        // Fetching order items in SP-API requires a separate call: /orders/v0/orders/{orderId}/orderItems
        // We mock one item here for completeness
        $order->addItem(new OrderItem($item['AmazonOrderId'].'-1', 'SKU-AMZ-MOCK', 'Mock Item', 1, (float)($item['OrderTotal']['Amount'] ?? 0.0)));

        return $order;
    }

    private function mockAmazonCatalogResponse(?string $nextToken): array
    {
        if ($nextToken) return ['items' => []]; // Empty on second page
        return [
            'items' => [
                [
                    'asin' => 'B00012345',
                    'summaries' => [['itemName' => 'Amazon Mock Product 1', 'brand' => 'AmazonBasics']],
                    'identifiers' => [['identifiers' => [['identifier' => 'SKU-AMZ-1', 'identifierType' => 'SKU']]]],
                    'images' => [['images' => [['link' => 'http://example.com/amz1.jpg']]]]
                ]
            ],
            'pagination' => ['nextToken' => 'mock-token']
        ];
    }

    private function mockAmazonOrdersResponse(?string $nextToken): array
    {
        if ($nextToken) return ['payload' => ['Orders' => []]]; // Empty on second page
        return [
            'payload' => [
                'Orders' => [
                    [
                        'AmazonOrderId' => '111-222-333',
                        'OrderStatus' => 'Shipped',
                        'OrderTotal' => ['Amount' => '50.00'],
                        'PurchaseDate' => '2026-06-01T09:00:00Z',
                        'BuyerInfo' => ['BuyerEmail' => 'test@test.com', 'BuyerName' => 'Test User'],
                        'ShippingAddress' => ['AddressLine1' => 'Test St', 'City' => 'Test City']
                    ]
                ],
                'NextToken' => 'mock-token'
            ]
        ];
    }
}
