<?php

namespace App\Domain\Import\PropertyGroup;

use App\Domain\Shopware6API;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class PropertyGroupImporter
{
    protected LoggerInterface $logger;
    protected Shopware6API $shopwareApi;
    protected LockProvider $lockProvider;
    protected LockNameGenerator $lockNameGenerator;
    protected int $lockTtlSeconds = 30;
    protected int $lockWaitSeconds = 5;

    public function __construct(
        LoggerInterface $logger,
        Shopware6API $shopware6API,
        LockProvider $lockProvider,
        ?LockNameGenerator $lockNameGenerator = null,
    ) {
        $this->logger = $logger;
        $this->shopwareApi = $shopware6API;
        $this->lockProvider = $lockProvider;
        $this->lockNameGenerator = $lockNameGenerator ?? new LockNameGeneratorAsciiGroupName();
    }

    public function import(string $groupName, array $optionNames): PropertyGroupDTO
    {
        // try to get through the import process without the need for a lock we could
        // call it the happy path -> the property group already exists and has
        // all the required options
        $propertyGroup = $this->shopwareApi->findPropertyGroupByName($groupName);
        if ($propertyGroup && $this->propertyGroupHasAllOptions($propertyGroup, $optionNames)) {
            $this->logger->info(__METHOD__ . ' Found property group and all requested options in shopware', [
                'groupName' => $groupName,
                'optionNames' => $optionNames,
            ]);

            return $propertyGroup;
        }

        // otherwise acquire a lock before creating entities in shopware
        $lock = $this->getLock($groupName);

        $this->logger->info(__METHOD__ . ' Try to acquire lock to create or update a property group', [
            'groupName' => $groupName,
        ]);

        $lockResult = $lock->block(
            $this->lockWaitSeconds,
            fn () => $this->createOrUpdate($groupName, $optionNames),
        );

        return $lockResult;
    }

    protected function createOrUpdate(string $groupName, array $optionNames): PropertyGroupDTO
    {
        $loggingContext = ['groupName' => $groupName];

        // try to find an existing property group by name otherwise create a new one
        if (!$propertyGroup = $this->shopwareApi->findPropertyGroupByName($groupName)) {
            $this->logger->info(__METHOD__ . " Couldn't find property group in shopware -> create it", $loggingContext);
            $propertyGroup = $this->shopwareApi->createPropertyGroup($groupName);
        }

        $loggingContext['groupId'] = $propertyGroup->getId();

        // find and create new options
        $newOptionNames = $this->determineNewOptionNames($propertyGroup, $optionNames);

        $this->logger->info(__METHOD__ . " Create new property group options", array_merge(
            $loggingContext,
            ['newOptionNames' => $newOptionNames],
        ));

        $newOptions = $newOptionNames
            ->map(function (string $optionName) use ($propertyGroup): PropertyGroupOptionDTO {
                return $this->shopwareApi->createPropertyGroupOption($propertyGroup->getId(), $optionName);
            });

        return new PropertyGroupDTOExtended($propertyGroup, $newOptions);
    }

    protected function propertyGroupHasAllOptions(PropertyGroupDTO $propertyGroup, array $optionNames): bool
    {
        $newOptionNames = $this->determineNewOptionNames($propertyGroup, $optionNames);

        return $newOptionNames->isEmpty();
    }

    protected function determineNewOptionNames(PropertyGroupDTO $propertyGroup, array $optionNames): Collection
    {
        $existingOptionNames = $propertyGroup->getOptions()
            ->map(fn(PropertyGroupOptionDTO $option) => $option->getName());

        return collect($optionNames)->diff($existingOptionNames);
    }

    protected function getLock(string $groupName): Lock
    {
        return $this->lockProvider->lock(
            ($this->lockNameGenerator)($groupName),
            $this->lockTtlSeconds,
        );
    }

    public function getLockTtlSeconds(): int
    {
        return $this->lockTtlSeconds;
    }

    public function setLockTtlSeconds(int $lockTtlSeconds): void
    {
        $this->lockTtlSeconds = $lockTtlSeconds;
    }

    public function getLockWaitSeconds(): int
    {
        return $this->lockWaitSeconds;
    }

    public function setLockWaitSeconds(int $lockWaitSeconds): void
    {
        $this->lockWaitSeconds = $lockWaitSeconds;
    }
}
