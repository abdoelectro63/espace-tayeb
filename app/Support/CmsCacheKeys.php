<?php

namespace App\Support;

final class CmsCacheKeys
{
    public const TTL_SECONDS = 3600;

    public static function menu(string $location): string
    {
        return "cms.menu.v2.{$location}";
    }

    public static function menusAll(): string
    {
        return 'cms.menus.all';
    }

    public static function page(string $slug): string
    {
        return "cms.page.v2.{$slug}";
    }
}
