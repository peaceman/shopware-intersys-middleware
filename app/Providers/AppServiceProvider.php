<?php

namespace App\Providers;

use App\Domain\Import\ImportFileScanner;
use App\Domain\Import\ModelXMLImporter;
use App\Domain\Import\SkippingImportFileScanner;
use App\Domain\ShopwareAPI;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
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
        $this->app->bind(ModelXMLImporter::class, function () {
            $branchesToImport = collect(explode(',', config('shopware.branchesToImport')))
                ->map(function ($branch) { return trim($branch); })
                ->toArray();

            $modelXMLImporter = new ModelXMLImporter($this->app[LoggerInterface::class], $this->app[ShopwareAPI::class]);
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
}
