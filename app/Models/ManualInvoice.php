<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualInvoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'invoice_date',
        'client_company_name',
        'client_ice',
        'client_if',
        'client_rc',
        'billing_address',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $invoice): void {
            if (filled($invoice->number)) {
                return;
            }

            $year = $invoice->invoice_date?->format('Y') ?? now()->format('Y');
            $invoice->number = sprintf('MF-%s-%05d', $year, $invoice->id);
            $invoice->saveQuietly();
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ManualInvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
