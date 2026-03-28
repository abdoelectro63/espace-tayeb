<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingCompanyCity extends Model
{
    protected $fillable = [
        'shipping_company_id',
        'name',
        'aliases',
        'vitips_label',
        'vitips_city_id',
        'express_city_code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function labelForVitips(): string
    {
        return filled($this->vitips_label) ? trim((string) $this->vitips_label) : trim((string) $this->name);
    }

    /**
     * Value for Vitips add-colis city fields.
     * Most deployments validate the dropdown label (e.g. CASABLANCA); some expect the option id — use VITIPS_CITY_VALUE=id.
     */
    public function cityValueForVitipsApi(): string
    {
        $mode = (string) config('services.vitips.city_value', 'label');

        if ($mode === 'id' && filled($this->vitips_city_id)) {
            return trim((string) $this->vitips_city_id);
        }

        return $this->labelForVitips();
    }
}
