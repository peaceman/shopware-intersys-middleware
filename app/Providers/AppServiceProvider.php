<?php

namespace App\Providers;

use App\Domain\Export\OrderReturnProvider;
use App\Domain\Export\OrderSaleProvider;
use App\Domain\Export\OrderXMLExporter;
use App\Domain\Export\OrderXMLGenerator;
use App\Domain\HouseKeeping\OldImportFileDeleter;
use App\Domain\HouseKeeping\OldImportFileProvider;
use App\Domain\Import\ImportFileScanner;
use App\Domain\Import\ModelXMLImporter;
use App\Domain\Import\SkippingImportFileScanner;
use App\Domain\OrderTracking\OrdersToCancelProvider;
use App\Domain\OrderTracking\UnpaidOrderCanceller;
use App\Domain\OrderTracking\UnpaidOrderProvider;
use App\Domain\ShopwareAPI;
use GuzzleHttp\Client;
use Illuminate\Database\ConnectionInterface;
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
        $this->registerUnpaidOrderProvider();
        $this->registerOrdersToCancelProvider();
        $this->registerUnpaidOrderCanceller();
        $this->registerOldImportFileProvider();
        $this->registerOldImportFileDeleter();
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
            $exporter->setAfterExportPositionStatusReturn(config('shopware.order.return.afterExportPositionStatus'));
            $exporter->setOrderPositionStatusRequirementReturn(config('shopware.order.return.requirements.positionStatus'));

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

    protected function registerUnpaidOrderProvider(): void
    {
        $this->app->extend(UnpaidOrderProvider::class, function (UnpaidOrderProvider $orderProvider): UnpaidOrderProvider {
            $orderProvider->setUnpaidPaymentStatusIDs(config('shopware.order.paymentStatus.unpaid'));

            return $orderProvider;
        });
    }

    protected function registerOrdersToCancelProvider(): void
    {
        $this->app->extend(
            OrdersToCancelProvider::class,
            function (OrdersToCancelProvider $orderProvider): OrdersToCancelProvider {
                $orderProvider->setUnpaidPaymentStatusIDs(config('shopware.order.paymentStatus.unpaid'));
                $orderProvider->setPrePaymentID(config('shopware.order.prePaymentId'));
                $orderProvider->setCancelWaitingTimeInDays(config('shopware.order.cancelWaitingTimeInDays'));

                return $orderProvider;
            }
        );
    }

    protected function registerUnpaidOrderCanceller(): void
    {
        $this->app->extend(UnpaidOrderCanceller::class, function (UnpaidOrderCanceller $canceller): UnpaidOrderCanceller {
            $canceller->setReturnOrderStatusRequirement(config('shopware.order.return.requirements.status'));
            $canceller->setReturnOrderPositionStatusRequirement(config('shopware.order.return.requirements.positionStatus'));

            return $canceller;
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
}
