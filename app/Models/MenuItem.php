<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MenuItem extends Model
{
    protected $fillable = [
        'menu_id',
        'label',
        'order',
        'linkable_id',
        'linkable_type',
        'custom_url',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolveUrl(): ?string
    {
        if (filled($this->custom_url)) {
            return $this->custom_url;
        }

        $linkable = $this->linkable;

        if ($linkable instanceof Page) {
            return route('page.show', ['slug' => $linkable->slug]);
        }

        if ($linkable instanceof Category) {
            return route('store.category', ['path' => $linkable->storePath()]);
        }

        return null;
    }
}
