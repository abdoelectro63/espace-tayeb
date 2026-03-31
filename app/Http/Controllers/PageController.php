<?php

namespace App\Http\Controllers;

use App\Services\MenuService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug, MenuService $menuService): View
    {
        $page = $menuService->publishedPageBySlug($slug);

        abort_if($page === null, 404);

        $metaTitle = $page->seo_title ?: $page->title;
        $metaDescription = $page->seo_description;

        return view('store.page', [
            'page' => $page,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
        ]);
    }
}
