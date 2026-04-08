<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Schemas\OrderInvoiceForm;
use App\Models\Order;
use App\Models\OrderProduct;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ManageOrderInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'Client et lignes (facture)';

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return false;
    }

    protected function authorizeAccess(): void
    {
        abort_unless(InvoiceResource::canViewAny(), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return InvoiceResource::canViewAny();
    }

    public function form(Schema $schema): Schema
    {
        return OrderInvoiceForm::configure($this->defaultForm($schema));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        $record->loadMissing(['orderItems.product', 'orderItems.productVariation']);

        $data['invoice_client_company_name'] = $record->invoice_client_company_name;
        $data['invoice_client_ice'] = $record->invoice_client_ice;
        $data['invoice_client_if'] = $record->invoice_client_if;
        $data['invoice_client_rc'] = $record->invoice_client_rc;
        $data['invoice_billing_address'] = $record->invoice_billing_address;

        $data['invoice_lines'] = $record->orderItems->map(function (OrderProduct $item): array {
            $catalog = $item->product?->name ?? 'Article';
            if ($item->productVariation) {
                $catalog .= ' — '.$item->productVariation->label();
            }

            return [
                'order_product_id' => $item->id,
                'catalog_label' => $catalog,
                'invoice_designation' => $item->invoice_designation,
            ];
        })->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $lines = $data['invoice_lines'] ?? [];
        unset($data['invoice_lines']);

        /** @var Order $record */
        $record->update([
            'invoice_client_company_name' => $data['invoice_client_company_name'] ?? null,
            'invoice_client_ice' => $data['invoice_client_ice'] ?? null,
            'invoice_client_if' => $data['invoice_client_if'] ?? null,
            'invoice_client_rc' => $data['invoice_client_rc'] ?? null,
            'invoice_billing_address' => $data['invoice_billing_address'] ?? null,
        ]);

        foreach ($lines as $line) {
            $id = $line['order_product_id'] ?? null;
            if (! is_numeric($id)) {
                continue;
            }
            OrderProduct::query()
                ->where('order_id', $record->id)
                ->whereKey((int) $id)
                ->update([
                    'invoice_designation' => filled($line['invoice_designation'] ?? null)
                        ? (string) $line['invoice_designation']
                        : null,
                ]);
        }

        return $record->refresh();
    }
}
