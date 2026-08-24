<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeExpiredTokens extends Command
{
    protected $signature = 'tokens:purge
        {--days=7 : Supprimer les tokens expirés depuis plus de N jours}
        {--revoked-hours=24 : Supprimer les tokens révoqués depuis plus de N heures}
        {--dry-run : Afficher le nombre de tokens à supprimer sans les supprimer}';

    protected $description = 'Supprime les tokens API expirés ou révoqués de la base de données.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $revokedHours = (int) $this->option('revoked-hours');
        $dryRun = (bool) $this->option('dry-run');

        $expiredQuery = ApiToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now()->subDays($days));

        $revokedQuery = ApiToken::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', Carbon::now()->subHours($revokedHours));

        $expiredCount = $expiredQuery->count();
        $revokedCount = $revokedQuery->count();
        $total = $expiredCount + $revokedCount;

        $this->info(sprintf(
            'Tokens expirés (> %d jours) : %d',
            $days,
            $expiredCount
        ));

        $this->info(sprintf(
            'Tokens révoqués (> %d heures) : %d',
            $revokedHours,
            $revokedCount
        ));

        if ($total === 0) {
            $this->info('Aucun token à supprimer.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn(sprintf('[dry-run] %d token(s) seraient supprimés.', $total));
            return self::SUCCESS;
        }

        $expiredQuery->delete();
        $revokedQuery->delete();

        $this->info(sprintf('%d token(s) supprimés avec succès.', $total));

        return self::SUCCESS;
    }
}
