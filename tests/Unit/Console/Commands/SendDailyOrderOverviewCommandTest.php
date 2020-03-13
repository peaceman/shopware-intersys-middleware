<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Console\Commands;

use App\Commands\SendDailyOrderOverview;
use App\Console\Commands\SendDailyOrderOverviewCommand;
use Tests\TestCase;

class SendDailyOrderOverviewCommandTest extends TestCase
{
    public function testExecution(): void
    {
        $sendDailyOrderOverview = $this->createMock(SendDailyOrderOverview::class);
        $sendDailyOrderOverview->expects(static::once())->method('__invoke');

        $command = $this->app->make(SendDailyOrderOverviewCommand::class);
        $command->handle($sendDailyOrderOverview);
    }
}
