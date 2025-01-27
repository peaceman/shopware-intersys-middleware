<?php

namespace App\Providers;

use App\Domain\Export\ShopwareOrderReturnProvider;
use App\Domain\Export\ShopwareOrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\Domain\Export\OrderXMLGenerator;
use App\Domain\HouseKeeping\OldImportFileDeleter;
use App\Domain\HouseKeeping\OldImportFileProvider;
use App\Domain\Import\ImportFileScanner;
use App\Domain\Import\Manufacturer\ManufacturerImporter;
use App\Domain\Import\Manufacturer\ManufacturerImporterImpl;
use App\Domain\Import\ModelImporter;
use App\Domain\Import\PropertyGroup\PropertyGroupImporter;
use App\Domain\Import\PropertyGroup\PropertyGroupImporterImpl;
use App\Domain\Import\SkippingImportFileScanner;
use App\Domain\Shopware6API;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\OAuth2\Client\OptionProvider\OptionProviderInterface;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Paginator::useBootstrap();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerShopwareAPI();
        $this->registerModelImporter();
        $this->registerImportFileScanner();
        $this->registerOrderProvider();
        $this->registerOrderXMLGenerator();
        $this->registerOrderXMLExporter();
        $this->registerOldImportFileProvider();
        $this->registerOldImportFileDeleter();

        $this->registerManufacturerImporter();
        $this->registerPropertyGroupImporter();
    }

    protected function registerShopwareAPI(): void
    {
        $this->app->bind(Shopware6API::class, function () {
            $oauthProvider = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId' => config('shopware.auth.clientId'),
                'clientSecret' => config('shopware.auth.clientSecret'),
                'urlAccessToken' => rtrim(config('shopware.baseUri'), '/') . '/api/oauth/token',
                'urlAuthorize' => config('shopware.baseUri'),
                'urlResourceOwnerDetails' => config('shopware.baseUri'),
            ]);

            $oauthProvider->setOptionProvider(new class implements OptionProviderInterface {
                public function getAccessTokenOptions($method, array $params)
                {
                    $options = ['headers' => ['content-type' => 'application/json'], 'body' => json_encode($params)];

                    return $options;
                }
            });

            $oauthTokenOptions = [
                'grant_type' => 'client_credentials',
            ];

            $guzzleOptions = [
                'base_uri' => config('shopware.baseUri'),
                'headers' => [
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                ],
            ];

            $httpClient = \Softonic\OAuth2\Guzzle\Middleware\ClientBuilder::build(
                $oauthProvider,
                $oauthTokenOptions,
                $this->app->get('cache.psr6'),
                $guzzleOptions,
            );

            $api = new Shopware6API($this->app[LoggerInterface::class], $httpClient);

            return $api;
        });
    }

    protected function registerModelImporter(): void
    {
        $this->app->extend(ModelImporter::class, function (ModelImporter $modelXMLImporter) {
            $modelXMLImporter->setGlnToImport(config('shopware.glnToImport'));
            $modelXMLImporter->setIgnoreStockUpdatesFromDelta(
                boolval(config('shopware.ignoreDeltaStockUpdates', false))
            );

            return $modelXMLImporter;
        });
    }

    protected function registerImportFileScanner(): void
    {
        $this->app->bind(ImportFileScanner::class, function () {
            $scanner = new ImportFileScanner(
                $this->app[LoggerInterface::class],
                Storage::disk('local'),
                Storage::disk('intersys')
            );

            $scanner->setFolder(config('intersys.folder.stock'));

            return $scanner;
        });

        $this->app->bind(SkippingImportFileScanner::class, function () {
            $scanner = new SkippingImportFileScanner(
                $this->app[LoggerInterface::class],
                Storage::disk('local'),
                Storage::disk('intersys')
            );

            $scanner->setFolder(config('intersys.folder.stock'));

            return $scanner;
        });
    }

    protected function registerOrderProvider(): void
    {
// todo help to sw6
//        $this->app->extend(OrderSaleProvider::class, function (OrderSaleProvider $osp) {
//            $osp->setRequirements(config('shopware.order.sale.requirements'));
//
//            return $osp;
//        });
//
//        $this->app->extend(OrderReturnProvider::class, function (OrderReturnProvider $osp) {
//            $osp->setRequirements(config('shopware.order.return.requirements'));
//
//            return $osp;
//        });
    }

    protected function registerOrderXMLExporter(): void
    {
// todo help to sw6
//        $this->app->bind(OrderXMLExporter::class, function () {
//            $exporter = new OrderXMLExporter(
//                $this->app[LoggerInterface::class],
//                Storage::disk('local'),
//                Storage::disk('intersys'),
//                $this->app[OrderXMLGenerator::class],
//                $this->app[ShopwareAPI::class]
//            );
//
//            $exporter->setBaseFolder(config('intersys.folder.order'));
//            $exporter->setOrderNumberPrefix(config('intersys.orderExport.file.numberPrefix'));
//            $exporter->setAfterExportStatusReturn(config('shopware.order.return.afterExportStatus'));
//            $exporter->setAfterExportStatusSale(config('shopware.order.sale.afterExportStatus'));
//            $exporter->setAfterExportPositionStatusReturn(config('shopware.order.return.afterExportPositionStatus'));
//            $exporter->setOrderPositionStatusRequirementReturn(config('shopware.order.return.requiredPositionStatus'));
//
//            return $exporter;
//        });
    }

    protected function registerOrderXMLGenerator(): void
    {
        $this->app->bind(OrderXMLGenerator::class, function () {
            $oxg = new OrderXMLGenerator();
            $oxg->setStockBranchNo(config('shopware.glnToImport'));

            return $oxg;
        });
    }

    protected function registerOldImportFileProvider(): void
    {
        $this->app->resolving(OldImportFileProvider::class, function (OldImportFileProvider $provider): void {
            $provider->setKeepDurationInDays(2 * 30);
        });
    }

    protected function registerOldImportFileDeleter(): void
    {
        $this->app->bind(OldImportFileDeleter::class, function () {
            return new OldImportFileDeleter(
                $this->app[LoggerInterface::class],
                Storage::disk('local'),
                $this->app[ConnectionInterface::class]
            );
        });
    }

    protected function registerManufacturerImporter(): void
    {
        $this->app->bind(ManufacturerImporter::class, function () {
            return new ManufacturerImporterImpl(
                $this->app[LoggerInterface::class],
                $this->app[Shopware6API::class],
                $this->app->get('cache')->driver()->getStore(),
            );
        });
    }

    protected function registerPropertyGroupImporter(): void
    {
        $this->app->bind(PropertyGroupImporter::class, function () {
            return new PropertyGroupImporterImpl(
                $this->app[LoggerInterface::class],
                $this->app[Shopware6API::class],
                $this->app->get('cache')->driver()->getStore(),
            );
        });
    }
}
