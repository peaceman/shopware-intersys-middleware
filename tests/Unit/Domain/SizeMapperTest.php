<?php
/**
 * lel since 31.10.18
 */
namespace Tests\Unit\Domain;

use App\Manufacturer;
use App\ManufacturerSizeMapping;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class SizeMapperTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * @var Manufacturer
     */
    protected $manufacturer;

    /**
     * @var \App\Domain\SizeMapper
     */
    protected $sizeMapper;

    protected function setUp()
    {
        parent::setUp();

        $this->manufacturer = factory(Manufacturer::class)->create();

        ManufacturerSizeMapping::unguarded(function () {
            $this->manufacturer->sizeMappings()->create([
                'gender' => ManufacturerSizeMapping::GENDER_CHILD,
                'source_size' => 4,
                'target_size' => 8,
            ]);

            $this->manufacturer->sizeMappings()->create([
                'gender' => ManufacturerSizeMapping::GENDER_FEMALE,
                'source_size' => 4,
                'target_size' => 12,
            ]);

            $this->manufacturer->sizeMappings()->create([
                'gender' => ManufacturerSizeMapping::GENDER_MALE_UNISEX,
                'source_size' => 4,
                'target_size' => 18,
            ]);

            $this->manufacturer->sizeMappings()->create([
                'gender' => ManufacturerSizeMapping::GENDER_MALE_UNISEX,
                'source_size' => 'XS',
                'target_size' => 'M',
            ]);
        });

        $this->sizeMapper = new \App\Domain\SizeMapper();
    }

    public function testSizeMapping()
    {
        // child
        static::assertEquals('8', $this->sizeMapper->mapSize($this->manufacturer->name, '215653', '4'));
        static::assertEquals('8', $this->sizeMapper->mapSize($this->manufacturer->name, '215656', '4'));
        static::assertEquals('8', $this->sizeMapper->mapSize($this->manufacturer->name, '215659', '4'));

        // female
        static::assertEquals('12', $this->sizeMapper->mapSize($this->manufacturer->name, '215652', '4'));
        static::assertEquals('12', $this->sizeMapper->mapSize($this->manufacturer->name, '215655', '4'));
        static::assertEquals('12', $this->sizeMapper->mapSize($this->manufacturer->name, '215658', '4'));

        // male
        static::assertEquals('18', $this->sizeMapper->mapSize($this->manufacturer->name, '215651', '4'));
        static::assertEquals('18', $this->sizeMapper->mapSize($this->manufacturer->name, '215654', '4'));
        static::assertEquals('18', $this->sizeMapper->mapSize($this->manufacturer->name, '215657', '4'));
    }

    public function testSizeMappingWithMissingFedas()
    {
        static::assertEquals('4', $this->sizeMapper->mapSize($this->manufacturer->name, null, '4'));
    }

    public function testSizeMappingWithUnknownSourceSize()
    {
        static::assertEquals('7', $this->sizeMapper->mapSize($this->manufacturer->name, '214653', '7'));
    }

    public function testSizeMappingWithUnknownManufacturer()
    {
        static::assertEquals('6', $this->sizeMapper->mapSize('unknown manufacturer', '214653', '6'));
    }

    public function testSizeMappingWithStrings()
    {
        static::assertEquals('M', $this->sizeMapper->mapSize($this->manufacturer->name, '214651', 'XS'));
        static::assertEquals('M', $this->sizeMapper->mapSize($this->manufacturer->name, '214651', ' xS'));
    }
}
