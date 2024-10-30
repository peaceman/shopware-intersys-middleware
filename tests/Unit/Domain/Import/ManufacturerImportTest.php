<?php

namespace Tests\Unit\Domain\Import;

use App\Domain\Import\LockNameGeneratorRaw;
use App\Domain\Import\Manufacturer\ManufacturerDTO;
use App\Domain\Import\Manufacturer\ManufacturerImporter;
use App\Domain\Shopware6API;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ManufacturerImportTest extends TestCase
{
    public function testImporterThrowsLockWaitExceptionWhenThereIsAnotherImportRunning(): void
    {
        $importer = new ManufacturerImporter(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            $lockProvider = new ArrayStore(),
            $lockNameGenerator = new LockNameGeneratorRaw(),
        );

        // set lock wait seconds to 0 to trigger the exception without wait
        $importer->setLockWaitSeconds(0);

        $manufacturerName = 'new manufacturer name';

        // define api mocks

        // trigger the "this is a new manufacturer" code path
        $swApiMock->expects(static::once())
            ->method('findManufacturerByName')
            ->willReturn(null);

        $lock = $lockProvider->lock($lockNameGenerator($manufacturerName));
        $lockResult = $lock->get();

        static::assertTrue($lockResult, 'failed to setup the intentionally blocking lock');
        static::expectException(LockTimeoutException::class);

        // call the implementation
        $importer->import($manufacturerName);
    }

    public function testManufacturerCreation(): void
    {
        $importer = new ManufacturerImporter(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            new ArrayStore(),
        );

        $manufacturerId = Str::random(40);
        $manufacturerName = 'new manufacturer name';

        // define api mocks
        $swApiMock->expects(static::once())
            ->method('createManufacturer')
            ->with($manufacturerName)
            ->willReturn(new ManufacturerDTO(['id' => $manufacturerId, 'name' => $manufacturerName]));

        // call the implementation
        $manufacturer = $importer->import($manufacturerName);

        // assertions
        static::assertInstanceOf(ManufacturerDTO::class, $manufacturer);

        // check that the created manufacturer has the expected name and an id
        static::assertEquals($manufacturerName, $manufacturer->getName());
        static::assertNotNull($manufacturer->getId());
    }

    public function testAlreadyExistingManufacturersWontBeDuplicated(): void
    {
        $importer = new ManufacturerImporter(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            new ArrayStore(),
        );

        $manufacturerName = 'new manufacturer name';

        // define api mocks
        $swApiMock->expects(static::once())
            ->method('findManufacturerByName')
            ->with($manufacturerName)
            ->willReturn(new ManufacturerDTO(['id' => Str::random(40), 'name' => $manufacturerName]));

        $swApiMock->expects(static::never())
            ->method('createManufacturer')
            ->withAnyParameters();

        // call the implementation
        $importer->import($manufacturerName);
    }

    public function testImporterRechecksManufacturerExistenceDuringLock(): void
    {
        $importer = new ManufacturerImporter(
            new NullLogger(),
            $swApiMock = $this->createMock(Shopware6API::class),
            $lockProvider = new ArrayStore(),
            $lockNameGenerator = new LockNameGeneratorRaw(),
        );

        $manufacturerName = 'new manufacturer name';

        // define api mocks
        $swApiMock->expects(static::exactly(2))
            ->method('findManufacturerByName')
            ->with($manufacturerName)
            ->willReturnOnConsecutiveCalls(
                null,
                new ManufacturerDTO(['name' => $manufacturerName]),
            );

        $swApiMock->expects(static::never())
            ->method('createManufacturer')
            ->with($manufacturerName);

        // call implementation
        $importer->import($manufacturerName);
    }
}
