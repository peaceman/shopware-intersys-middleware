<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Console\Commands;

use App\Commands\ExportOrders as ExportOrdersAppCmd;
use App\Console\Commands\ExportOrdersCommand;
use Tests\TestCase;

class ExportOrdersCommandTest extends TestCase
{
    public function testExecution(): void
    {
        $exportOrders = $this->createMock(ExportOrdersAppCmd::class);
        $exportOrders->expects(static::once())->method('__invoke');

        /** @var ExportOrdersCommand $exportOrdersCLICommand */
        $exportOrdersCLICommand = $this->app->make(ExportOrdersCommand::class);
        $exportOrdersCLICommand->handle($exportOrders);
    }
}
