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
        $response = $this->httpClient->post('/api/product', [
            'json' => $productData,
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return new ProductDTO($responseData);
    }

    public function updateProduct(string $id, array $productData): ProductDTO
    {
        $response = $this->httpClient->patch("/api/product/{$id}", [
            'json' => $productData,
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return new ProductDTO($responseData);
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

    public function createPropertyGroupOption(string $propertyGroupId, string $name): PropertyGroupOptionDTO
    {
        $response = $this->httpClient->post('/api/property-group-option', [
            'json' => [
                'groupId' => $propertyGroupId,
                'name' => $name,
            ],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $data = $responseBody['data'] ?? [];

        return new PropertyGroupOptionDTO($data);
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

        return new ProductDTO($responseData);
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
}
