<?php

namespace App\Console\Commands;

use App\Models\ShippingInvoiceImportLine;
use Illuminate\Console\Command;

class PruneShippingInvoiceNotFoundLines extends Command
{
    protected $signature = 'shipping-invoice-imports:prune-not-found-lines
                            {--dry-run : Afficher le nombre sans supprimer}';

    protected $description = 'Supprime les أسطر فاتورة الشحن dont la مطابقة est « غير موجود » (match_status = not_found).';

    public function handle(): int
    {
        $query = ShippingInvoiceImportLine::query()->where('match_status', 'not_found');
        $count = $query->count();

        if ($count === 0) {
            $this->info('Aucune ligne avec match_status = not_found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Serait supprimé : {$count} ligne(s).");

            return self::SUCCESS;
        }

        $deleted = ShippingInvoiceImportLine::query()->where('match_status', 'not_found')->delete();
        $this->info("Supprimé : {$deleted} ligne(s) (غير موجود / not_found).");

        return self::SUCCESS;
    }
}
