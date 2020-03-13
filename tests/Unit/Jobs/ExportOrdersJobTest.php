<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Jobs;

use App\Commands\ExportOrders as ExportOrdersAppCmd;
use App\Jobs\ExportOrdersJob;
use Tests\TestCase;

class ExportOrdersJobTest extends TestCase
{
    public function testExecution(): void
    {
        $exportOrders = $this->createMock(ExportOrdersAppCmd::class);
        $exportOrders->expects(static::once())->method('__invoke');

        /** @var ExportOrdersJob $exportOrdersJob */
        $exportOrdersJob = $this->app->make(ExportOrdersJob::class);
        $exportOrdersJob->handle($exportOrders);
    }
}
