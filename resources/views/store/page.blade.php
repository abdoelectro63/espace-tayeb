<x-layouts.store :title="$metaTitle" :metaDescription="$metaDescription ?? null">
    <article class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-14">
        <header class="mb-10 border-b border-zinc-200 pb-8">
            <p class="text-sm font-medium text-[#ff751f]">Espace Tayeb</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">{{ $page->title }}</h1>
        </header>

        @if(filled($page->content))
            <div class="prose prose-zinc max-w-none prose-headings:scroll-mt-24 prose-headings:font-bold prose-h2:text-xl prose-h2:text-zinc-900 prose-p:text-zinc-700 prose-li:text-zinc-700 prose-strong:text-zinc-900">
                {!! $page->content !!}
            </div>
        @endif

        <x-store.page-sections :sections="$page->sections ?? []" />
    </article>
</x-layouts.store>
