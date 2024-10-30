<?php

namespace App\Domain;
use App\Domain\Import\Manufacturer\ManufacturerDTO;
use App\Domain\Import\PropertyGroup\PropertyGroupDTO;
use App\Domain\Import\PropertyGroup\PropertyGroupDTORaw;
use App\Domain\Import\PropertyGroup\PropertyGroupOptionDTO;
use GuzzleHttp\Client;
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

    public function createProduct(array $productData): string
    {
        $response = $this->httpClient->post('/api/product', [
            'json' => $productData,
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        $id = $responseData['id'] ?? null;

        if (empty($id)) {
            $errorMessage = 'Failed to retrieve product id from creation response';
            $this->logger->error($errorMessage, [
                'responseBody' => $responseBody,
            ]);

            throw new \RuntimeException($errorMessage);
        }

        return $id;
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

    public function searchManufacturerIdByName(string $name): ?string
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

        return $manufacturer['id'] ?? null;
    }

    public function createManufacturer(string $name): ManufacturerDTO
    {
        $response = $this->httpClient->post('/api/product-manufacturer', [
            'json' => ['name' => $name],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        // todo implement
        return new ManufacturerDTO($responseData);
    }

    public function searchPropertyGroupByName(string $name): ?array
    {
        $response = $this->httpClient->post('/api/search/property-group', [
            'json' => [
                'filter' => [
                    ['type' => 'equals', 'field' => 'name', 'value' => $name],
                ],
                'associations' => ['options' => []],
            ],
//            'query' => ['_response' => 'detail'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];
        if (empty($responseData)) return null;

        [$propertyGroup] = $responseData;

        return $propertyGroup;
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

    /**
     * @deprecated
     */
    public function createPropertyGroupOptionRaw(string $propertyGroupId, string $name): array
    {
        $response = $this->httpClient->post('/api/property-group-option', [
            'json' => [
                'groupId' => $propertyGroupId,
                'name' => $name,
            ],
            'query' => ['_response' => 'basic'],
        ]);

        $responseBody = Utils::jsonDecode($response->getBody(), true);
        $responseData = $responseBody['data'] ?? [];

        return $responseData;
    }

    public function findManufacturerByName(string $name): ?ManufacturerDTO
    {
        // todo implement

        return new ManufacturerDTO();
    }
}
