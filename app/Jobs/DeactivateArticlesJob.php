<?php
/**
 * lel since 13.03.20
 */

namespace App\Jobs;

use App\Commands\DeactivateArticles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeactivateArticlesJob implements ShouldQueue
{
    use Queueable;

    public function handle(DeactivateArticles $deactivateArticles): void
    {
        $deactivateArticles();
    }
}
