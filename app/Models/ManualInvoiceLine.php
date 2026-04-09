<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualInvoiceLine extends Model
{
    protected $fillable = [
        'manual_invoice_id',
        'sort_order',
        'designation',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function manualInvoice(): BelongsTo
    {
        return $this->belongsTo(ManualInvoice::class);
    }
}
