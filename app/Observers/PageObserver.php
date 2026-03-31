<?php

namespace App\Observers;

use App\Models\Page;
use App\Services\MenuService;

class PageObserver
{
    public function __construct(
        protected MenuService $menuService
    ) {}

    public function saving(Page $page): void
    {
        if ($page->isDirty('slug') && filled($page->getOriginal('slug'))) {
            $this->menuService->forgetPageCaches((string) $page->getOriginal('slug'));
        }
    }

    public function saved(Page $page): void
    {
        $this->menuService->forgetPageCaches($page->slug);
    }

    public function deleted(Page $page): void
    {
        $this->menuService->forgetPageCaches($page->slug);
    }
}
