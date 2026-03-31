<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Page;
use App\Support\CmsCacheKeys;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    public function menuForLocation(string $location): ?Menu
    {
        /** @var int|null $menuId */
        $menuId = Cache::remember(
            CmsCacheKeys::menu($location),
            CmsCacheKeys::TTL_SECONDS,
            fn (): ?int => Menu::query()->where('location', $location)->value('id')
        );

        if ($menuId === null) {
            return null;
        }

        return Menu::query()
            ->whereKey($menuId)
            ->with([
                'items' => fn ($query) => $query->orderBy('order')->with('linkable'),
            ])
            ->first();
    }

    public function publishedPageBySlug(string $slug): ?Page
    {
        /** @var int|null $pageId */
        $pageId = Cache::remember(
            CmsCacheKeys::page($slug),
            CmsCacheKeys::TTL_SECONDS,
            fn (): ?int => Page::query()
                ->where('slug', $slug)
                ->where('is_published', true)
                ->value('id')
        );

        if ($pageId === null) {
            return null;
        }

        return Page::query()
            ->whereKey($pageId)
            ->with('media')
            ->first();
    }

    public function forgetMenuCaches(): void
    {
        foreach (array_keys(Menu::locationOptions()) as $location) {
            Cache::forget(CmsCacheKeys::menu($location));
        }

        Cache::forget(CmsCacheKeys::menusAll());
    }

    public function forgetPageCaches(string $slug): void
    {
        Cache::forget(CmsCacheKeys::page($slug));
    }
}
