<?php
/**
 * lel since 13.03.20
 */

namespace Tests\Unit\Console\Commands;

use App\Commands\TrackUnpaidOrders;
use App\Console\Commands\TrackUnpaidOrdersCommand;
use Tests\TestCase;

class TrackUnpaidOrdersCommandTest extends TestCase
{
    public function testExecution(): void
    {
        $trackUnpaidOrders = $this->createMock(TrackUnpaidOrders::class);
        $trackUnpaidOrders->expects(static::once())->method('__invoke');

        $trackUnpaidOrdersCommand = $this->app->make(TrackUnpaidOrdersCommand::class);
        $trackUnpaidOrdersCommand->handle($trackUnpaidOrders);
    }
}
