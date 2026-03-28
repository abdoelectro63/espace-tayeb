<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingInvoiceImportLine extends Model
{
    protected $fillable = [
        'shipping_invoice_import_id',
        'carrier',
        'tracking_key',
        'order_id',
        'customer_name',
        'city',
        'total_price',
        'invoice_frais',
        'etat',
        'match_status',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
            'invoice_frais' => 'integer',
            'collected_at' => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ShippingInvoiceImport::class, 'shipping_invoice_import_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
