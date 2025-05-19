<?php

namespace App\Domain\Import\PropertyGroup;

use App\Domain\Import\LockNameGenerator;
use App\Domain\Shopware6API;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

class PropertyGroupImporterImpl implements PropertyGroupImporter
{
    protected LoggerInterface $logger;
    protected Shopware6API $shopwareApi;
    protected LockProvider $lockProvider;
    protected LockNameGenerator $lockNameGenerator;
    protected int $lockTtlSeconds = 30;
    protected int $lockWaitSeconds = 5;
    protected OptionPositionAdvisor $optionPositionAdvisor;

    public function __construct(
        LoggerInterface $logger,
        Shopware6API $shopware6API,
        LockProvider $lockProvider,
        ?LockNameGenerator $lockNameGenerator = null,
        ?OptionPositionAdvisor $optionPositionAdvisor = null,
    ) {
        $this->logger = $logger;
        $this->shopwareApi = $shopware6API;
        $this->lockProvider = $lockProvider;
        $this->lockNameGenerator = $lockNameGenerator ?? new LockNameGeneratorAsciiGroupName();
        $this->optionPositionAdvisor = $optionPositionAdvisor ?? new OptionPositionAdvisorFixed(1);
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

        if (!$newOptionNames->isEmpty()) {
            $this->logger->info(__METHOD__ . " Create new property group options", array_merge(
                $loggingContext,
                ['newOptionNames' => $newOptionNames->toArray()],
            ));
        }

        $newOptions = $newOptionNames
            ->map(function (string $optionName) use ($propertyGroup): PropertyGroupOptionDTO {
                return $this->shopwareApi->createPropertyGroupOption($propertyGroup->getId(), [
                    'name' => $optionName,
                    'position' => ($this->optionPositionAdvisor)($optionName),
                ]);
            });

        // check for and update changed options
        $changedOptions = $this->determineChangedOptions($propertyGroup, []);

        if (!$changedOptions->isEmpty()) {
            $this->logger->info(__METHOD__ . " Update property group", [
                ...$loggingContext,
                ...['changedOptions' => $changedOptions->toArray()]
            ]);
            $this->shopwareApi->updatePropertyGroup($propertyGroup->getId(), ['options' => $changedOptions->toArray()]);
        }

        return new PropertyGroupDTOExtended($propertyGroup, $newOptions);
    }

    protected function propertyGroupHasAllOptions(PropertyGroupDTO $propertyGroup, array $optionNames): bool
    {
        $newOptionNames = $this->determineNewOptionNames($propertyGroup, $optionNames);
        $changedOptions = $this->determineChangedOptions($propertyGroup, $optionNames);

        return $newOptionNames->isEmpty() && $changedOptions->isEmpty();
    }

    protected function determineNewOptionNames(PropertyGroupDTO $propertyGroup, array $optionNames): Collection
    {
        $existingOptionNames = $propertyGroup->getOptions()
            ->map(fn(PropertyGroupOptionDTO $option) => $option->getName());

        return collect($optionNames)->diff($existingOptionNames);
    }

    protected function determineChangedOptions(PropertyGroupDTO $propertyGroup, array $optionNames): Collection
    {
        $optionsWithPositions = collect($optionNames)
            ->merge($propertyGroup->getOptions()->map(fn (PropertyGroupOptionDTO $option): string => $option->getName()))
            ->unique()
            ->map(fn(string $name): array => ['name' => $name, 'position' => ($this->optionPositionAdvisor)($name)]);

        $changedOptions = $optionsWithPositions
            ->filter(function (array $optionData) use ($propertyGroup): bool {
                if (!($oldOption = $propertyGroup->getOptionByName($optionData['name'])))
                    return false;

                return $oldOption->getPosition() !== $optionData['position'];
            })
            ->map(function (array $optionData) use ($propertyGroup): array {
                return [
                    'id' => $propertyGroup->getOptionByName($optionData['name'])->getId(),
                    'position' => $optionData['position'],
                ];
            });

        return $changedOptions;
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
