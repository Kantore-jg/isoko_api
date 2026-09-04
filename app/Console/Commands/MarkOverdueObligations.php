<?php

namespace App\Console\Commands;

use App\Models\RentObligation;
use Illuminate\Console\Command;

class MarkOverdueObligations extends Command
{
    protected $signature = 'rent:mark-overdue';

    protected $description = 'Marque les obligations de loyer PENDING ou PARTIAL comme OVERDUE quand la date d\'échéance est dépassée.';

    public function handle(): int
    {
        $today = now()->toDateString();

        $updated = RentObligation::query()
            ->whereIn('status', ['PENDING', 'PARTIAL'])
            ->where('balance', '>', 0)
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'OVERDUE']);

        $this->info("Obligations marquées OVERDUE : {$updated}");

        return self::SUCCESS;
    }
}
