<?php

namespace App\Console\Commands;

use App\Services\OlseraService;
use Illuminate\Console\Command;

class OlseraSyncCommand extends Command
{
    protected $signature = 'olsera:sync {--date= : Specific date (Y-m-d), default today} {--days=1 : Sync N days back}';
    protected $description = 'Sync Olsera sales data to local cache (olsera_daily_sales)';

    public function handle(OlseraService $olsera): int
    {
        $specificDate = $this->option('date');
        $days = (int) $this->option('days');

        if ($specificDate) {
            $this->info("Syncing {$specificDate}...");
            $olsera->syncDaily($specificDate);
            $this->info("✅ Done.");
            return 0;
        }

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $this->info("Syncing {$date}...");
            $olsera->syncDaily($date);
        }

        $this->info("✅ Synced {$days} day(s).");
        return 0;
    }
}
