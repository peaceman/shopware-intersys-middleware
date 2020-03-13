<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Jobs;

use App\Commands\DeactivateArticles as DeactivateArticlesAppCmd;
use App\Jobs\DeactivateArticlesJob;
use Tests\TestCase;

class DeactivateArticlesJobTest extends TestCase
{
    public function testExecution(): void
    {
        $deactivateArticles = $this->createMock(DeactivateArticlesAppCmd::class);
        $deactivateArticles->expects(static::once())->method('__invoke');

        $deactivateArticlesJob = $this->app->make(DeactivateArticlesJob::class);
        $deactivateArticlesJob->handle($deactivateArticles);
    }
}
