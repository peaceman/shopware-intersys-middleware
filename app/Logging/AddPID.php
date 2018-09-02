<?php
/**
 * lel since 02.09.18
 */
namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Processor\ProcessIdProcessor;

class AddPID
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new ProcessIdProcessor());
    }
}