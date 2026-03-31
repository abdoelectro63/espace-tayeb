<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Services\MenuService;

class MenuItemObserver
{
    public function __construct(
        protected MenuService $menuService
    ) {}

    public function saved(MenuItem $menuItem): void
    {
        $this->menuService->forgetMenuCaches();
    }

    public function deleted(MenuItem $menuItem): void
    {
        $this->menuService->forgetMenuCaches();
    }
}
