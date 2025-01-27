<?php

namespace App\Domain\Export;

enum OrderExportType: string
{
    case Return = 'return';
    case Sale = 'sale';
}
