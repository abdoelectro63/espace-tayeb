<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingInvoiceImport extends Model
{
    protected $fillable = [
        'user_id',
        'carrier_filter',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShippingInvoiceImportLine::class);
    }

    public function vitipsLines(): HasMany
    {
        return $this->lines()->where('carrier', 'vitips');
    }

    public function expressLines(): HasMany
    {
        return $this->lines()->where('carrier', 'express');
    }

    public function eligibleVitipsCount(): int
    {
        return $this->vitipsLines()
            ->where('match_status', 'eligible')
            ->whereNull('collected_at')
            ->count();
    }

    public function eligibleExpressCount(): int
    {
        return $this->expressLines()
            ->where('match_status', 'eligible')
            ->whereNull('collected_at')
            ->count();
    }
}
