<?php

namespace App\Console\Commands;

use App\Models\ShippingInvoiceImport;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supprime les imports sans ligne « utile » (même règle que la liste : pas seulement not_found / already_paid).
 */
class PruneEmptyShippingInvoiceImports extends Command
{
    protected $signature = 'shipping-invoice-imports:prune-empty-imports
                            {--dry-run : Afficher le nombre sans supprimer}';

    protected $description = 'Supprime les استيرادات sans أسطر فاتورة affichables (0 ligne utile).';

    public function handle(): int
    {
        $query = ShippingInvoiceImport::query()->whereDoesntHave(
            'lines',
            fn (Builder $q): Builder => $q->whereNotIn('match_status', ['not_found', 'already_paid'])
        );

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Aucun import à supprimer.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Serait supprimé : {$count} import(s).");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Supprimé : {$deleted} import(s).");

        return self::SUCCESS;
    }
}
