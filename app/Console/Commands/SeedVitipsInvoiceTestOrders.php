<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ShippingCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedVitipsInvoiceTestOrders extends Command
{
    /**
     * All Vitips tracking numbers to seed (Code d'envoi).
     *
     * @var list<string>
     */
    private const TRACKINGS = [
        'CL-9082631457',
        'CL-5274983061',
        'CL-6731295084',
        'CL-1209758643',
        'CL-1487629530',
        'CL-8459612370',
        'CL-1496587032',
        'CL-7489051326',
        'CL-3245710698',
        'CL-9635872041',
        'CL-1394508276',
        'CL-7153849062',
        'CL-9587406231',
        'CL-2680419375',
        'CL-1653089247',
        'CL-0496287351',
        'CL-6512047389',
        'CL-9801534627',
        'CL-0714965328',
        'CL-5603417982',
        'CL-3187690254',
        'CL-9572086134',
        'CL-8013579426',
        'CL-9457603821',
        'CL-6752904318',
        'CL-0764183592',
        'CL-8164932075',
        'CL-3509748162',
        'CL-7169804253',
        'CL-8746925130',
        'CL-7912840365',
        'CL-5236849701',
        'CL-4037291685',
        'CL-2186304795',
        'CL-5391680247',
        'CL-9402386715',
        'CL-2641075938',
        'CL-2718059364',
        'CL-6987413250',
        'CL-2145960387',
        'CL-1635987420',
        'CL-4095871623',
        'CL-2518936740',
    ];

    /** @var list<string> */
    private const CITIES = [
        'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda',
        'Kenitra', 'Tétouan', 'Safi', 'Mohammedia', 'El Jadida', 'Beni Mellal', 'Nador',
    ];

    protected $signature = 'orders:seed-vitips-invoice-test
                            {--fresh : Supprimer les commandes marquées vitips-seed ou vitips-invoice-test puis recréer}
                            {--company=Vitips : Nom exact de shipping_company}';

    protected $description = 'Crée ou met à jour des commandes Vitips (mêmes CL-…), statut shipped + unpaid pour tester l’import de facture Vitips Express.';

    public function handle(): int
    {
        $companyName = (string) $this->option('company');

        $company = ShippingCompany::query()->firstOrCreate(
            ['name' => $companyName],
            ['color' => 'warning'],
        );

        if ($this->option('fresh')) {
            // SoftDelete laisse la ligne : le numéro reste pris → UNIQUE sur `number` échoue au prochain insert.
            // Inclut les trackings de test même si `notes` a été modifié (anciennes passes de seed).
            Order::withTrashed()
                ->where(function ($q): void {
                    $q->whereIn('notes', ['vitips-seed', 'vitips-invoice-test'])
                        ->orWhereIn('tracking_number', self::TRACKINGS);
                })
                ->forceDelete();
            $this->info('Anciennes commandes vitips-seed / vitips-invoice-test (ou trackings de test) supprimées définitivement.');
        }

        $product = Product::query()->where('is_active', true)->first()
            ?? Product::query()->first();

        if ($product === null) {
            $this->error('Aucun produit en base : crée au moins un produit avant.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($company, $companyName, $product, &$created, &$updated): void {
            foreach (self::TRACKINGS as $i => $tracking) {
                $city = self::CITIES[$i % count(self::CITIES)];
                $phone = '06'.str_pad((string) (($i * 791 + 1234567) % 100000000), 8, '0', STR_PAD_LEFT);
                $number = $this->uniqueOrderNumberForTracking($tracking);

                $existing = Order::withTrashed()
                    ->where('tracking_number', $tracking)
                    ->first();

                if ($existing !== null && $existing->trashed()) {
                    $existing->restore();
                }

                $payload = [
                    'customer_name' => 'Client Vitips '.$tracking,
                    'customer_phone' => $phone,
                    'shipping_address' => 'Adresse seed, '.$city,
                    'city' => $city,
                    'shipping_zone' => 'other',
                    'shipping_fee' => 0,
                    'total_price' => 120.00,
                    'status' => 'shipped',
                    'payment_status' => 'unpaid',
                    'paid_at' => null,
                    'delivery_man_id' => null,
                    'shipping_company' => $companyName,
                    'shipping_company_id' => $company->id,
                    'tracking_number' => $tracking,
                    'shipping_provider_status' => 'Le colis est ajouté avec succes',
                    'notes' => 'vitips-seed',
                ];

                if ($existing !== null) {
                    $existing->update($payload);
                    $this->ensureOrderHasLine($existing, $product);
                    $updated++;

                    continue;
                }

                $order = Order::query()->create(array_merge($payload, [
                    'number' => $number,
                ]));

                OrderProduct::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 120.00,
                ]);

                $created++;
            }
        });

        $this->info("Créées : {$created} | mises à jour : {$updated}. Statut : shipped + unpaid, {$companyName} — prêt pour import facture (Vitips Express uniquement si besoin).");

        return self::SUCCESS;
    }

    /**
     * Un numéro par tracking (suffixe CL-…) pour éviter les collisions avec l’ancien format VIT-DLV-0001-…
     * et les lignes encore présentes en soft delete.
     */
    private function uniqueOrderNumberForTracking(string $tracking): string
    {
        $suffix = preg_replace('/^CL-/i', '', $tracking);
        $base = 'VIT-DLV-'.$suffix;

        // `tracking_number != ?` n’inclut pas les NULL → risque de faux « pas de conflit ».
        $conflict = Order::withTrashed()
            ->where('number', $base)
            ->where(function ($q) use ($tracking): void {
                $q->whereNull('tracking_number')
                    ->orWhere('tracking_number', '!=', $tracking);
            })
            ->exists();

        if ($conflict) {
            return $base.'-'.Str::lower(Str::random(4));
        }

        return $base;
    }

    private function ensureOrderHasLine(Order $order, Product $product): void
    {
        if ($order->orderItems()->exists()) {
            return;
        }

        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 120.00,
        ]);
    }
}
