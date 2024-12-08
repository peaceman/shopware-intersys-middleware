<?php

namespace Tests\Unit\Domain\Import;

use App\Domain\Import\LockNameGeneratorRaw;
use App\Domain\Import\PropertyGroup\PropertyGroupDTO;
use App\Domain\Import\PropertyGroup\PropertyGroupDTORaw;
use App\Domain\Import\PropertyGroup\PropertyGroupImporterImpl;
use App\Domain\Import\PropertyGroup\PropertyGroupOptionDTO;
use App\Domain\Shopware6API;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PropertyGroupImporterTest extends TestCase
{
    public function testImporterThrowsLockWaitExceptionWhenThereIsAnotherImportRunning(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            $lockProvider = new ArrayStore(),
            new LockNameGeneratorRaw()
        );

        $importer->setLockWaitSeconds(0);

        $propertyGroupName = 'new property group name';

        // define api mocks
        $swApiMock->expects(static::once())
            ->method('findPropertyGroupByName')
            ->willReturn(null);

        $swApiMock->expects(static::never())
            ->method('createPropertyGroup');

        // time to lock it up
        $lock = $lockProvider->lock($propertyGroupName);
        $lockResult = $lock->get();

        static::assertTrue($lockResult, 'failed to setup the intentionally blocking lock');
        static::expectException(LockTimeoutException::class);

        // call the implementation
        $importer->import($propertyGroupName, []);
    }

    public function testImporterRefreshesPropertyGroupDataWhileLockedDuringTheCreateOrUpdatePhase(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            new ArrayStore(),
        );

        $propertyGroupLookupResponse = json_decode(fixture_content('shopware/property-group-lookup-response.json'), true);
        $propertyGroupName = $propertyGroupLookupResponse['data'][0]['name'];
        $optionNames = [
            $propertyGroupLookupResponse['data'][0]['options'][0]['name'],
            'not so new option name',
            'new for real',
        ];

        // define api mocks
        $swApiMock->expects(static::exactly(2))
            ->method('findPropertyGroupByName')
            ->with($propertyGroupName)
            ->willReturnOnConsecutiveCalls(
                new PropertyGroupDTORaw($propertyGroupLookupResponse['data'][0]),
                // sneak in a new option
                new PropertyGroupDTORaw(array_merge_recursive($propertyGroupLookupResponse['data'][0], [
                    'options' => [['id' => Str::random(40), 'name' => 'not so new option name']],
                ])),
            );

        $swApiMock->expects(static::once())
            ->method('createPropertyGroupOption')
            ->with(static::anything(), 'new for real')
            ->willReturnCallback(fn (string $groupId, string $optionName) => new PropertyGroupOptionDTO([
                'groupId' => $groupId,
                'id' => Str::random(40),
                'name' => $optionName,
            ]));

        // call implementation
        $importer->import($propertyGroupName, $optionNames);
    }

    public function testImporterLocksForNewPropertyGroup(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            $lockProviderMock = $this->createMock(LockProvider::class),
            new LockNameGeneratorRaw(),
        );

        $propertyGroupName = 'property group name';
        $propertyGroupOptionName = 'property group option name';

        // define api mocks
        $swApiMock->expects(static::atLeastOnce())
            ->method('findPropertyGroupByName')
            ->withAnyParameters()
            ->willReturn(null);

        $swApiMock->expects(static::once())
            ->method('createPropertyGroup')
            ->with($propertyGroupName)
            ->willReturn(new PropertyGroupDTORaw(['id' => Str::random(40), 'name' => $propertyGroupName]));

        $lockProviderMock->expects(static::once())
            ->method('lock')
            ->with(
                $propertyGroupName,
                static::greaterThan(0), // lock should be auto released after a certain time, just in case
            )
            ->willReturn($lockMock = $this->createMock(Lock::class));

        $lockMock->expects(static::once())
            ->method('block')
            ->with($importer->getLockWaitSeconds(), static::callback(fn(mixed $v): bool => is_callable($v)))
            ->willReturnCallback(fn (int $ttl, callable $fn) => $fn());

        // call the implementation
        $importer->import($propertyGroupName, [$propertyGroupOptionName]);
    }

    public function testImporterDoesntLockForKnownPropertyGroupAndOptions(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            $lockMock = $this->createMock(LockProvider::class),
        );

        $propertyGroupLookupResponse = json_decode(fixture_content('shopware/property-group-lookup-response.json'), true);
        $propertyGroupName = $propertyGroupLookupResponse['data'][0]['name'];
        $existingPropertyGroupOptionName = $propertyGroupLookupResponse['data'][0]['options'][0]['name'];

        $swApiMock->expects(static::atLeastOnce())
            ->method('findPropertyGroupByName')
            ->with($propertyGroupName)
            ->willReturn(new PropertyGroupDTORaw($propertyGroupLookupResponse['data'][0]));

        $lockMock->expects(static::never())
            ->method('lock')
            ->withAnyParameters();

        // call the implementation
        $importer->import($propertyGroupName, [$existingPropertyGroupOptionName]);
    }

    public function testPropertyGroupImportWithoutNewOptions(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            $this->createMock(LockProvider::class),
        );

        $propertyGroupLookupResponse = json_decode(fixture_content('shopware/property-group-lookup-response.json'), true);
        $propertyGroupName = $propertyGroupLookupResponse['data'][0]['name'];
        $existingPropertyGroupOptionName = $propertyGroupLookupResponse['data'][0]['options'][0]['name'];

        // define api mocks

        // should never call create methods of the api because we have no new data
        $swApiMock->expects(static::never())
            ->method('createPropertyGroup');

        $swApiMock->expects(static::never())
            ->method('createPropertyGroupOption');

        $swApiMock->expects(static::atLeastOnce())
            ->method('findPropertyGroupByName')
            ->with($propertyGroupName)
            ->willReturn(new PropertyGroupDTORaw($propertyGroupLookupResponse['data'][0]));

        // call the implementation
        $propertyGroup = $importer->import($propertyGroupName, [$existingPropertyGroupOptionName]);

        // assertions

        // check that the property group contains all options
        static::assertSameSize(
            $propertyGroupLookupResponse['data'][0]['options'],
            $propertyGroup->getOptions(),
            'amount of options in the shopware options doesnt equal the amount of options in the returned dto'
        );
    }

    public function testPropertyGroupUpdateWithNewOptions(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            new ArrayStore(),
        );

        $propertyGroupLookupResponse = json_decode(fixture_content('shopware/property-group-lookup-response.json'), true);
        $propertyGroupName = $propertyGroupLookupResponse['data'][0]['name'];
        $propertyGroupId = $propertyGroupLookupResponse['data'][0]['id'];
        $newPropertyGroupOptionName = 'new option name';

        // define api mocks

        // should never call the createPropertyGroup method of the api because we are updating an existing one
        $swApiMock->expects(static::never())
            ->method('createPropertyGroup');

        $swApiMock->expects(static::atLeastOnce())
            ->method('findPropertyGroupByName')
            ->with($propertyGroupName)
            ->willReturn(new PropertyGroupDTORaw($propertyGroupLookupResponse['data'][0]));

        $swApiMock->expects(static::once())
            ->method('createPropertyGroupOption')
            ->with($propertyGroupId, $newPropertyGroupOptionName)
            ->willReturn(new PropertyGroupOptionDTO([
                'groupId' => $propertyGroupId,
                'id' => Str::random(40),
                'name' => $newPropertyGroupOptionName,
            ]));

        // call the implementation
        $propertyGroup = $importer->import($propertyGroupName, [$newPropertyGroupOptionName]);

        // assertions

        // check that the property group contains all existing and the new option
        $options = $propertyGroup->getOptions();
        static::assertCount(count($propertyGroupLookupResponse['data'][0]['options']) + 1, $options);
        static::assertNotNull($options->first(fn (PropertyGroupOptionDTO $option): bool => $option->getName() === $newPropertyGroupOptionName));
    }

    public function testPropertyGroupCreation(): void
    {
        $importer = new PropertyGroupImporterImpl(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            new ArrayStore(),
        );

        $propertyGroupId = Str::random(40);
        $propertyGroupName = 'Size';
        $propertyGroupOptionNames = ['M', 'L'];

        // define api mocks
        $swApiMock->expects(static::once())
            ->method('createPropertyGroup')
            ->with($propertyGroupName)
            ->willReturn(new PropertyGroupDTORaw(['id' => $propertyGroupId, 'name'=> $propertyGroupName]));

        $swApiMock->expects(static::exactly(count($propertyGroupOptionNames)))
            ->method('createPropertyGroupOption')
            ->with($propertyGroupId, static::anything())
            ->willReturnCallback(function (string $groupId, string $optionName): PropertyGroupOptionDTO {
                return new PropertyGroupOptionDTO(['groupId' => $groupId, 'id' => Str::random(40), 'name' => $optionName]);
            });

        // call the implementation
        $propertyGroup = $importer->import($propertyGroupName, $propertyGroupOptionNames);

        // assertions
        static::assertInstanceOf(PropertyGroupDTO::class, $propertyGroup);

        // check that the created or retrieved property group has the expected name and got an id
        static::assertEquals($propertyGroupName, $propertyGroup->getName());
        static::assertNotNull($propertyGroup->getId());

        // check that the created or retrieved property group contains all requested options
        $propertyGroupOptions = $propertyGroup->getOptions();
        static::assertInstanceOf(Enumerable::class, $propertyGroupOptions);
        static::assertCount(count($propertyGroupOptionNames), $propertyGroupOptions);

        foreach ($propertyGroupOptionNames as $propertyGroupOptionName) {
            $propertyGroupOption = $propertyGroupOptions
                ->first(fn(PropertyGroupOptionDTO $pgo): bool => $pgo->getName() === $propertyGroupOptionName);

            static::assertNotNull($propertyGroupOption);
            static::assertNotNull($propertyGroupOption->getId());
        }
    }
}
