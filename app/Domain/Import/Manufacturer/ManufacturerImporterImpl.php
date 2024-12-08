<?php

namespace App\Domain\Import\Manufacturer;

use App\Domain\Import\LockNameGenerator;
use App\Domain\Import\PrefixedLockNameGenerator;
use App\Domain\Shopware6API;
use Illuminate\Contracts\Cache\LockProvider;
use Psr\Log\LoggerInterface;

class ManufacturerImporterImpl implements ManufacturerImporter
{
    protected LoggerInterface $logger;
    protected Shopware6API $shopwareApi;
    protected LockProvider $lockProvider;
    protected LockNameGenerator $lockNameGenerator;

    protected int $lockTtlSeconds = 30;
    protected int $lockWaitSeconds = 5;

    public function __construct(
        LoggerInterface $logger,
        Shopware6API $shopwareApi,
        LockProvider $lockProvider,
        ?LockNameGenerator $lockNameGenerator = null,
    ) {
        $this->logger = $logger;
        $this->shopwareApi = $shopwareApi;
        $this->lockProvider = $lockProvider;
        $this->lockNameGenerator = $lockNameGenerator ?? new PrefixedLockNameGenerator('manufacturer');
    }

    public function setLockTtlSeconds(int $lockTtlSeconds): void
    {
        $this->lockTtlSeconds = $lockTtlSeconds;
    }

    public function setLockWaitSeconds(int $lockWaitSeconds): void
    {
        $this->lockWaitSeconds = $lockWaitSeconds;
    }

    public function import(string $manufacturerName): ManufacturerDTO
    {
        $manufacturer = $this->shopwareApi->findManufacturerByName($manufacturerName);
        if ($manufacturer) return $manufacturer;

        // acquire a lock before creating entities in shopware
        $lock = $this->lockProvider->lock(
            ($this->lockNameGenerator)($manufacturerName),
            $this->lockTtlSeconds,
        );

        $this->logger->info(__METHOD__ . ' Try to acquire lock to create a manufacturer', [
            'manufacturerName' => $manufacturerName,
        ]);

        $lockResult = $lock->block(
            $this->lockWaitSeconds,
            fn () => $this->findOrCreateManufacturer($manufacturerName),
        );

        return $lockResult;
    }

    protected function findOrCreateManufacturer(string $manufacturerName): ManufacturerDTO
    {
        $loggingContext = ['manufacturerName' => $manufacturerName];

        if (!$manufacturer = $this->shopwareApi->findManufacturerByName($manufacturerName)) {
            $this->logger->info(__METHOD__ . "Couldn't find manufacturer in shopware -> create it", $loggingContext);
            $manufacturer = $this->shopwareApi->createManufacturer($manufacturerName);
        }

        return $manufacturer;
    }
}
