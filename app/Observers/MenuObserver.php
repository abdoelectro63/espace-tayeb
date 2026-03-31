<?php

namespace App\Observers;

use App\Models\Menu;
use App\Services\MenuService;

class MenuObserver
{
    public function __construct(
        protected MenuService $menuService
    ) {}

    public function saved(Menu $menu): void
    {
        $this->menuService->forgetMenuCaches();
    }

    public function deleted(Menu $menu): void
    {
        $this->menuService->forgetMenuCaches();
    }
}
