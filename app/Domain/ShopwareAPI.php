<?php
/**
 * lel since 20.08.18
 */

namespace App\Domain;


use App\Article;
use App\Domain\Import\ShopwareArticleInfo;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

class ShopwareAPI
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * ShopwareAPI constructor.
     * @param LoggerInterface $logger
     * @param Client $httpClient
     */
    public function __construct(LoggerInterface $logger, Client $httpClient)
    {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    public function searchShopwareArticleIdByArticleNumber(string $articleNumber): ?int
    {
        try {
            $response = $this->httpClient->get("/api/articles/{$articleNumber}", [
                'query' => [
                    'useNumberAsId' => true,
                ],
            ]);

            $swArticleData = json_decode($response->getBody());
            $swArticleId = data_get($swArticleData, 'data.id');

            if ($swArticleId) {
                $this->logger->info(__METHOD__ . ' Found article in shopware', [
                    'articleNumber' => $articleNumber,
                    'swArticleId' => $swArticleId,
                ]);
            }

            return $swArticleId;
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $this->logger->info(__METHOD__ . ' Article does not exist in shopware', [
                    'articleNumber' => $articleNumber,
                ]);

                return null;
            }

            throw $e;
        }
    }

    public function updateShopwareArticle(int $swArticleId, array $articleData): void
    {
        $response = $this->httpClient->put("/api/articles/{$swArticleId}", [
            'json' => $articleData
        ]);
    }

    public function createShopwareArticle(array $articleData): int
    {
        $response = $this->httpClient->post('/api/articles', [
            'json' => $articleData
        ]);

        $articleData = json_decode($response->getBody());

        return $articleData->data->id;
    }

    public function deactivateShopwareArticle(int $swArticleId): void
    {
        $response = $this->httpClient->put("/api/articles/{$swArticleId}", [
            'json' => [
                'active' => false,
            ],
        ]);
    }

    public function searchShopwareArticleInfoByArticle(Article $article): ?ShopwareArticleInfo
    {
        try {
            $response = $this->httpClient->get("/api/articles/{$article->sw_article_id}");

            $swArticleData = json_decode($response->getBody(), true);
            $swArticleInfo = new ShopwareArticleInfo($swArticleData);

            if ($swArticleInfo) {
                $this->logger->info(__METHOD__ . ' Found article in shopware', [
                    'articleNumber' => $article->is_modno,
                    'swArticleId' => $swArticleInfo->getMainDetailArticleId(),
                ]);
            }

            return $swArticleInfo;
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                $this->logger->info(__METHOD__ . ' Article does not exist in shopware', [
                    'articleNumber' => $article->is_modno,
                ]);

                return null;
            }

            throw $e;
        }
    }

    public function fetchOrders(array $filters): ?array
    {
        $loggingContext = ['filters' => $filters];

        try {
            $this->logger->info(__METHOD__, $loggingContext);
            $response = $this->httpClient->get('/api/orders', [
                'query' => [
                    'filter' => $filters,
                ],
            ]);

            return \GuzzleHttp\json_decode((string)$response->getBody(), true);
        } catch (BadResponseException | \InvalidArgumentException $e) {
            $this->logger->warning(__METHOD__ . ' Failed to fetch orders from shopware', $loggingContext);
            report($e);

            return null;
        }
    }

    public function fetchOrderDetails(int $orderID): ?array
    {
        $loggingContext = ['orderID' => $orderID];

        try {
            $this->logger->info(__METHOD__, $loggingContext);
            $response = $this->httpClient->get("/api/orders/{$orderID}");

            return \GuzzleHttp\json_decode((string)$response->getBody(), true);
        } catch (BadResponseException | \InvalidArgumentException $e) {
            $this->logger->warning(__METHOD__ . ' Failed to fetch order details from shopware', $loggingContext);
            report($e);

            return null;
        }
    }

    public function updateOrderStatus(int $orderID, int $newStatusID, array $positionIDs, int $newPositionStatusID)
    {
        $loggingContext = [
            'orderID' => $orderID, 'newStatusID' => $newStatusID,
            'positionIDs' => $positionIDs, 'newPositionStatusID' => $newPositionStatusID
        ];
        $this->logger->info(__METHOD__, $loggingContext);

        $response = $this->httpClient->put("/api/orders/{$orderID}", [
            'json' => [
                'orderStatusId' => $newStatusID,
                'details' => array_map(function (int $positionID) use ($newPositionStatusID) {
                    return [
                        'id' => $positionID,
                        'status' => $newPositionStatusID,
                    ];
                }, $positionIDs),
            ],
        ]);
    }
}
