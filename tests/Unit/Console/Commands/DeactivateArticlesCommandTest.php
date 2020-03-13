<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Console\Commands;

use App\Commands\DeactivateArticles as DeactivateArticlesAppCmd;
use App\Console\Commands\DeactivateArticlesCommand;
use Tests\TestCase;

class DeactivateArticlesCommandTest extends TestCase
{
    public function testExecution(): void
    {
        $deactivateArticles = $this->createMock(DeactivateArticlesAppCmd::class);
        $deactivateArticles->expects(static::once())->method('__invoke');

        $deactivateArticlesCLICommand = $this->app->make(DeactivateArticlesCommand::class);
        $deactivateArticlesCLICommand->handle($deactivateArticles);
    }
}
