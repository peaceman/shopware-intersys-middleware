<?php
/**
 * lel since 20.08.18
 */
namespace Tests\Unit\Domain\ArticleDeactivation;

use App\Article;
use App\Domain\Import\ArticleDeactivation\AbandonedArticleIDProvider;
use App\ImportFile;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class AbandonedArticleIDProviderTest extends TestCase
{
    use DatabaseMigrations;

    public function testThatItProvidesArticleIDsThatWereNotInTheLast7ImportFiles()
    {
        $importFiles = $this->createImportFiles(8, 'base');
        $deltaImportFiles = $this->createImportFiles(8, 'delta');
        $articles = factory(Article::class, 3)->create();
        $inActiveArticles = factory(Article::class)->create(['is_active' => false]);
        $withoutSWArticleId = factory(Article::class)->create(['sw_article_id' => null]);

        $articles[1]->imports()->create(['import_file_id' => $importFiles[0]->id]);
        $articles[2]->imports()->create(['import_file_id' => $importFiles[7]->id]);

        $articleIDProvider = new AbandonedArticleIDProvider();
        $articleIDs = $articleIDProvider->getArticleIDs();

        static::assertEquals([$articles[0]->id, $articles[2]->id], iterator_to_array($articleIDs));
    }

    protected function createImportFiles(int $amount, string $type): iterable
    {
        $startDate = new \DateTimeImmutable('2018-05-23 23:05', new \DateTimeZone('+01:00'));
        $datePeriod = new \DatePeriod($startDate, new \DateInterval('P1D'), $amount);

        return collect($datePeriod)
            ->map(function (\DateTimeImmutable $importDate) use ($type) {
                $if = new ImportFile([
                    'type' => $type,
                    'original_filename' => $importDate->format('Y-m-d-H-i') . '.xml',
                    'storage_path' => str_random(54),
                ]);

                $if->save();

                return $if;
            });
    }
}