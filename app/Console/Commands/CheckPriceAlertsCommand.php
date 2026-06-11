<?php

namespace App\Console\Commands;

use App\Services\PriceAlertChecker;
use Illuminate\Console\Command;

class CheckPriceAlertsCommand extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Vérifie les alertes prix et envoie les notifications';

    public function handle(PriceAlertChecker $checker): int
    {
        $this->info('Vérification des alertes prix...');

        $triggered = $checker->checkAll();

        $this->info("{$triggered} alerte(s) déclenchée(s).");

        return self::SUCCESS;
    }
}
