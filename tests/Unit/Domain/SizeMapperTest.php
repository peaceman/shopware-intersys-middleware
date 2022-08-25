<?php
/**
 * lel since 31.10.18
 */
namespace Tests\Unit\Domain;

use App\Domain\Import\SizeMapper;
use App\Domain\Import\SizeMappingRequest;
use App\Manufacturer;
use App\ManufacturerSizeMapping;
use App\SizeMappingExclusion;
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
     * @var SizeMapper
     */
    protected $sizeMapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manufacturer = Manufacturer::factory()->create();

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

        foreach (['ex-main', 'ex-variant'] as $articleNumber) {
            SizeMappingExclusion::create(['article_number' => $articleNumber]);
        }

        $this->sizeMapper = new SizeMapper();
    }

    public function testSizeMapping()
    {
        // child
        static::assertEquals('8', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215653',
        )));
        static::assertEquals('8', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215656'
        )));
        static::assertEquals('8', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215659'
        )));

        // female
        static::assertEquals('12', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215652'
        )));
        static::assertEquals('12', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215655'
        )));
        static::assertEquals('12', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215658'
        )));

        // male
        static::assertEquals('18', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215651'
        )));
        static::assertEquals('18', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215654'
        )));
        static::assertEquals('18', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: '215657'
        )));
    }

    public function testSizeMappingWithMissingFedas()
    {
        static::assertEquals('4', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '4',
            fedas: null
        )));
    }

    public function testSizeMappingWithUnknownSourceSize()
    {
        static::assertEquals('7', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '7',
            fedas: '214653'
        )));
    }

    public function testSizeMappingWithUnknownManufacturer()
    {
        static::assertEquals('6', $this->sizeMapper->mapSize(new SizeMappingRequest(
            'unknown manufacturer',
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: '6',
            fedas: '214653'
        )));
    }

    public function testSizeMappingWithStrings()
    {
        static::assertEquals('M', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: 'XS',
            fedas: '214651'
        )));
        static::assertEquals('M', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'variant',
            size: ' xS',
            fedas: '214651'
        )));
    }

    public function testMappingExclusion(): void
    {
        static::assertEquals('XS', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'ex-main',
            variantArticleNumber: 'variant',
            size: 'XS',
            fedas: '214651'
        )));

        static::assertEquals('XS', $this->sizeMapper->mapSize(new SizeMappingRequest(
            $this->manufacturer->name,
            mainArticleNumber: 'main',
            variantArticleNumber: 'ex-variant',
            size: 'XS',
            fedas: '214651'
        )));
    }
}
