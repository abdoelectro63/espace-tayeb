<?php

namespace App\Console\Commands;

use App\Models\OrderCarrierCitySelection;
use App\Models\OrderProduct;
use App\Models\ShippingCompany;
use App\Models\ShippingInvoiceImport;
use App\Models\ShippingInvoiceImportLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime toutes les commandes et données associées (lignes, factures import, sélections ville),
 * efface les tokens / store_id des sociétés de livraison (sync API), puis recrée les commandes
 * Express Coursier de test et optionnellement appelle l’API (--sync).
 */
class OrdersResetAllAndSeedExpressCoursier extends Command
{
    protected $signature = 'orders:reset-all-and-seed-express
                            {--force : Obligatoire — confirme la suppression totale}
                            {--sync : Après création, envoyer les colis à l’API Express Coursier}
                            {--company=Express Coursier : Nom de la société de livraison}';

    protected $description = 'Efface toutes les commandes + factures import + liaisons, reset tokens transporteurs, puis seed Express Coursier (trackings fixes).';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Utilisez --force pour confirmer la suppression de TOUTES les commandes et données liées.');

            return self::FAILURE;
        }

        $this->warn('Suppression en cours…');

        $this->wipeOrderRelatedTables();

        $this->clearShippingCompanySyncColumns();
        $this->info('Sociétés de livraison : colonnes de sync API effacées (reconfigurez les tokens dans l’admin si besoin).');

        $this->info('Recréation des commandes Express Coursier…');

        $params = [
            '--company' => (string) $this->option('company'),
        ];
        if ($this->option('sync')) {
            $params['--sync'] = true;
        }

        return $this->call('orders:seed-express-coursier-test', $params);
    }

    private function wipeOrderRelatedTables(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable('shipping_invoice_import_lines')) {
                ShippingInvoiceImportLine::query()->delete();
            }
            if (Schema::hasTable('shipping_invoice_imports')) {
                ShippingInvoiceImport::query()->delete();
            }
            if (Schema::hasTable('order_carrier_city_selections')) {
                OrderCarrierCitySelection::query()->delete();
            }
            if (Schema::hasTable('order_products')) {
                OrderProduct::query()->delete();
            }
            if (Schema::hasTable('order_product')) {
                DB::table('order_product')->delete();
            }
            if (Schema::hasTable('order_items')) {
                DB::table('order_items')->delete();
            }
            if (Schema::hasTable('orders')) {
                DB::table('orders')->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->info('Tables vidées : commandes, lignes de commande, sélections ville transporteur, imports de factures.');
    }

    private function clearShippingCompanySyncColumns(): void
    {
        if (! Schema::hasTable('shipping_companies')) {
            return;
        }

        $payload = [];
        foreach (['store_id', 'token', 'delivery_token', 'vitips_token'] as $column) {
            if (Schema::hasColumn('shipping_companies', $column)) {
                $payload[$column] = null;
            }
        }

        if ($payload === []) {
            return;
        }

        ShippingCompany::query()->update($payload);
    }
}
