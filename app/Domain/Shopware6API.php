<?php

namespace App\Domain;
use App\Domain\Export\Order;
use App\Domain\Export\OrderDTO;
use App\Domain\Import\Manufacturer\ManufacturerDTO;
use App\Domain\Import\ProductDTO;
use App\Domain\Import\PropertyGroup\PropertyGroupDTO;
use App\Domain\Import\PropertyGroup\PropertyGroupDTORaw;
use App\Domain\Import\PropertyGroup\PropertyGroupOptionDTO;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Utils;
use Psr\Log\LoggerInterface;

class Shopware6API
{
    protected LoggerInterface $logger;
    protected Client $httpClient;

    public function __construct(LoggerInterface $logger, Client $httpClient)
    {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    public function listProducts(): void
    {
        $response = $this->httpClient->get('/api/product');
        $data = json_decode($response->getBody());
        dd($data);
    }

    public function createProduct(array $productData): ProductDTO
    {
        try {
            $response = $this->httpClient->post('/api/product', [
                'json' => $productData,
                'query' => ['_response' => 'basic'],
            ]);

            $responseBody = Utils::jsonDecode($response->getBody(), true);
            $responseData = $responseBody['data'] ?? [];

            return new ProductDTO($responseData);
        } catch (BadResponseException $e) {
            $this->logger->error(__METHOD__ . ' ' . $e->getMessage(), [
                'request' => (string)$e->getRequest()->getBody(),
                'response' => (string)$e->getResponse()->getBody(),
            ]);

            throw $e;
        }
    }

    public function updateProduct(string $id, array $productData): ProductDTO
    {
        try {
            $response = $this->httpClient->patch("/api/product/{$id}", [
                'json' => $productData,
                'query' => ['_response' => 'basic'],
            ]);

            $responseBody = Utils::jsonDecode($response->getBody(), true);
            $responseData = $responseBody['data'] ?? [];

            return new ProductDTO($responseData);
        } catch (BadResponseException $e) {
            $this->logger->error(__METHOD__ . ' ' . $e->getMessage(), [
                'request' => (string)$e->getRequest()->getBody(),
                'response' => (string)$e->getResponse()->getBody(),
            ]);

            throw $e;
        }
    }

    public function searchCurrencyIdByIsoCode(string $isoCode): ?string
    {
        $response = $this->httpClient->post('/api/search/currency', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'isoCode', 'value' => $isoCode],
                ],
                'limit' => 1,
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$currency] = $responseData;

        return $currency['id'] ?? null;
    }

    public function searchTaxIdByVatPercentage(float $vatPercentage): ?string
    {
        $response = $this->httpClient->post('/api/search/tax', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'taxRate', 'value' => $vatPercentage],
                ],
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$currency] = $responseData;

        return $currency['id'] ?? null;
    }

    public function findManufacturerByName(string $name): ?ManufacturerDTO
    {
        $response = $this->httpClient->post('/api/search/product-manufacturer', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'name', 'value' => $name],
                ],
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$manufacturer] = $responseData;

        return new ManufacturerDTO($manufacturer);
    }

    public function createManufacturer(string $name): ManufacturerDTO
    {
        $response = $this->httpClient->post('/api/product-manufacturer', [
            'json' => ['name' => $name],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return new ManufacturerDTO($responseData);
    }

    public function findPropertyGroupByName(string $name): ?PropertyGroupDTO
    {
        $response = $this->httpClient->post('/api/search/property-group', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'name', 'value' => $name],
                ],
                'associations' => ['options' => []],
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$propertyGroup] = $responseData;

        return new PropertyGroupDTORaw($propertyGroup);
    }

    public function createPropertyGroup(string $name): PropertyGroupDTO
    {
        $response = $this->httpClient->post('/api/property-group', [
            'json' => [
                'name' => $name,
            ],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return new PropertyGroupDTORaw($responseData);
    }

    public function createPropertyGroupOption(string $propertyGroupId, array $optionData): PropertyGroupOptionDTO
    {
        $response = $this->httpClient->post('/api/property-group-option', [
            'json' => [
                'groupId' => $propertyGroupId,
                ...$optionData,
            ],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $data = $responseBody['data'] ?? [];

        return new PropertyGroupOptionDTO($data);
    }

    public function updatePropertyGroup(string $propertyGroupId, array $data): PropertyGroupDTO
    {
        $response = $this->httpClient->patch("/api/property-group/{$propertyGroupId}", [
            'json' => [
                ...$data,
                'id' => $propertyGroupId,
            ],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return new PropertyGroupDTORaw($responseData);
    }

    public function findProductByProductNumber(string $productNumber): ?ProductDTO
    {
        $response = $this->httpClient->post('/api/search/product', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'productNumber', 'value' => $productNumber],
                ],
                'limit' => 1,
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$product] = $responseData;

        return new ProductDTO($product);
    }

    public function findProductById(string $id): ?ProductDTO
    {
        $response = $this->httpClient->post('/api/search/product', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'id', 'value' => $id],
                ],
                'limit' => 1,
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$product] = $responseData;

        return new ProductDTO($product);
    }

    public function getProductById(string $id): ?ProductDTO
    {
        $response = $this->httpClient->post('/api/search/product', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'id', 'value' => $id],
                ],
                'associations' => [
                    'children' => ['associations' => ['options' => []]],
                    'configuratorSettings' => [],
                ],
                'limit' => 1,
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$product] = $responseData;

        return new ProductDTO($product);
    }

    /**
     * @throws GuzzleException
     */
    public function deleteProductVariantOption(string $productId, string $variantId, string $optionId): void
    {
        $this->httpClient
            ->delete("/api/product/{$productId}/children/{$variantId}/options/{$optionId}");
    }

    public function updateOrderState(string $orderId, string $stateTransition): void
    {
        $this->httpClient
            ->post("/api/_action/order/{$orderId}/state/{$stateTransition}");
    }

    public function listOrders(array $criteria = []): array
    {
        $response = $this->httpClient->post('/api/search/order', [
            'json' => $criteria,
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return array_map(
            fn (array $v): OrderDTO => new Order($v),
            $responseData,
        );
    }

    public function listCompletedReturnOrdersRaw(): array
    {
        $response = $this->httpClient->post('/api/search/pickware-erp-return-order', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'state.technicalName', 'value' => 'completed'],
                    ['type' => 'equals', 'field' => 'intersys.exportedAt', 'value' => null],
                ],
                'associations' => [
                    'lineItems' => [
                        'associations' => [
                            'product' => [],
                        ],
                    ],
                    'intersys' => [],
                    'sourceStockMovements' => [],
                    'order' => [],
                ],
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);

        return $responseBody;
    }

    public function updateOrder(string $orderId, array $data): void
    {
        $this->httpClient
            ->patch("/api/order/{$orderId}", [
                'json' => $data,
            ]);
    }

    public function updateReturnOrder(string $returnOrderId, array $data): void
    {
        $this->httpClient
            ->patch("/api/pickware-erp-return-order/{$returnOrderId}", [
                'json' => $data,
            ]);
    }

    public function searchDeliveryTimeIdByMinMax(int $min, int $max)
    {
        $response = $this->httpClient->post('/api/search/delivery-time', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'min', 'value' => $min],
                    ['type' => 'equals', 'field' => 'max', 'value' => $max],
                ],
                'limit' => 1,
            ],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$deliveryTime] = $responseData;

        return $deliveryTime['id'] ?? null;
    }
}
