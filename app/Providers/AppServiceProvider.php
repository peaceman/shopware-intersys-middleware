<?php

namespace App\Providers;

use App\Domain\Export\OrderReturnProvider;
use App\Domain\Export\OrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\Domain\Export\OrderXMLGenerator;
use App\Domain\Import\ImportFileScanner;
use App\Domain\Import\ModelXMLImporter;
use App\Domain\Import\SkippingImportFileScanner;
use App\Domain\ShopwareAPI;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
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
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerShopwareAPI();
        $this->registerModelXMLImporter();
        $this->registerImportFileScanner();
        $this->registerOrderProvider();
        $this->registerOrderXMLGenerator();
        $this->registerOrderXMLExporter();
    }

    protected function registerShopwareAPI(): void
    {
        $this->app->bind(ShopwareAPI::class, function () {
            $httpClient = new Client([
                'base_uri' => config('shopware.baseUri'),
                'auth' => [config('shopware.auth.username'), config('shopware.auth.apiKey')],
            ]);

            $api = new ShopwareAPI($this->app[LoggerInterface::class], $httpClient);
            return $api;
        });
    }

    protected function registerModelXMLImporter(): void
    {
        $this->app->extend(ModelXMLImporter::class, function (ModelXMLImporter $modelXMLImporter) {
            $branchesToImport = collect(explode(',', config('shopware.branchesToImport')))
                ->map(function ($branch) { return trim($branch); })
                ->toArray();

            $modelXMLImporter->setBranchesToImport($branchesToImport);

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

            $scanner->setBaseFileFolder(config('intersys.folder.base'));
            $scanner->setDeltaFileFolder(config('intersys.folder.delta'));

            return $scanner;
        });

        $this->app->bind(SkippingImportFileScanner::class, function () {
            $scanner = new SkippingImportFileScanner(
                $this->app[LoggerInterface::class],
                Storage::disk('local'),
                Storage::disk('intersys')
            );

            $scanner->setBaseFileFolder(config('intersys.folder.base'));
            $scanner->setDeltaFileFolder(config('intersys.folder.delta'));

            return $scanner;
        });
    }

    protected function registerOrderProvider(): void
    {
        $this->app->extend(OrderSaleProvider::class, function (OrderSaleProvider $osp) {
            $osp->setSaleRequirements(config('shopware.order.sale.requirements'));

            return $osp;
        });

        $this->app->extend(OrderReturnProvider::class, function (OrderReturnProvider $osp) {
            $osp->setReturnRequirements(config('shopware.order.return.requirements'));

            return $osp;
        });
    }

    protected function registerOrderXMLExporter(): void
    {
        $this->app->bind(OrderXMLExporter::class, function () {
            $exporter = new OrderXMLExporter(
                $this->app[LoggerInterface::class],
                Storage::disk('local'),
                Storage::disk('intersys'),
                $this->app[OrderXMLGenerator::class],
                $this->app[ShopwareAPI::class]
            );

            $exporter->setBaseFolder(config('intersys.folder.order'));
            $exporter->setAfterExportStatusReturn(config('shopware.order.return.afterExportStatus'));
            $exporter->setAfterExportStatusSale(config('shopware.order.sale.afterExportStatus'));

            return $exporter;
        });
    }

    protected function registerOrderXMLGenerator(): void
    {
        $this->app->bind(OrderXMLGenerator::class, function () {
            $oxg = new OrderXMLGenerator();
            $oxg->setAccountingBranchNo(config('shopware.order.branchNoAccounting'));
            $oxg->setStockBranchNo(config('shopware.order.branchNoStock'));

            return $oxg;
        });
    }
}
