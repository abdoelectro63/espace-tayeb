<?php

namespace App\Console\Commands;

use App\Models\ShippingInvoiceImportLine;
use Illuminate\Console\Command;

class PruneShippingInvoiceAlreadyPaidLines extends Command
{
    protected $signature = 'shipping-invoice-imports:prune-already-paid-lines
                            {--dry-run : Afficher le nombre sans supprimer}';

    protected $description = 'Supprime les أسطر dont la مطابقة est « مدفوع مسبقاً — مرفوض » (match_status = already_paid).';

    public function handle(): int
    {
        $query = ShippingInvoiceImportLine::query()->where('match_status', 'already_paid');
        $count = $query->count();

        if ($count === 0) {
            $this->info('Aucune ligne avec match_status = already_paid.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Serait supprimé : {$count} ligne(s).");

            return self::SUCCESS;
        }

        $deleted = ShippingInvoiceImportLine::query()->where('match_status', 'already_paid')->delete();
        $this->info("Supprimé : {$deleted} ligne(s) (already_paid / مدفوع مسبقاً — مرفوض).");

        return self::SUCCESS;
    }
}
