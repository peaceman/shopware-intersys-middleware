<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Jobs;

use App\Commands\SendDailyOrderOverview;
use App\Jobs\SendDailyOrderOverviewJob;
use Tests\TestCase;

class SendDailyOrderOverviewJobTest extends TestCase
{
    public function testExecution(): void
    {
        $sendDailyOrderOverview = $this->createMock(SendDailyOrderOverview::class);
        $sendDailyOrderOverview->expects(static::once())->method('__invoke');

        $job = $this->app->make(SendDailyOrderOverviewJob::class);
        $job->handle($sendDailyOrderOverview);
    }
}
