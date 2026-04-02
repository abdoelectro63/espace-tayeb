<x-layouts.store
    :title="$metaTitle"
    :metaDescription="$metaDescription ?? null"
    :canonical="route('store.contact')"
>
    <div class="border-b border-zinc-200 bg-gradient-to-b from-white to-zinc-50/80">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
            <p class="text-sm font-medium text-[#ff751f]">Espace Tayeb</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                {{ $contact->page_title }}
            </h1>
            <p class="mt-3 max-w-2xl text-base text-zinc-600">
                نحن هنا للإجابة عن استفساراتكم. يمكنكم التواصل معنا عبر الهاتف، البريد، أو زيارة عنوان المتجر أدناه.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:py-16">
        <div class="grid gap-8 lg:grid-cols-2 lg:gap-12">
            <div class="space-y-4">
                @if(filled($contact->phone))
                    <a
                        href="tel:{{ preg_replace('/\s+/', '', $contact->phone) }}"
                        class="group flex gap-4 rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm ring-1 ring-zinc-100 transition hover:border-orange-200/80 hover:shadow-md"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#ff751f]/10 text-[#ff751f] ring-1 ring-[#ff751f]/20">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 text-start">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">الهاتف</span>
                            <span class="mt-1 block text-lg font-semibold text-zinc-900 group-hover:text-[#ff751f]">{{ $contact->phone }}</span>
                        </span>
                    </a>
                @endif

                @if(filled($contact->email))
                    <a
                        href="mailto:{{ $contact->email }}"
                        class="group flex gap-4 rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm ring-1 ring-zinc-100 transition hover:border-orange-200/80 hover:shadow-md"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 text-start">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">البريد الإلكتروني</span>
                            <span class="mt-1 block break-all text-lg font-semibold text-zinc-900 group-hover:text-[#ff751f]">{{ $contact->email }}</span>
                        </span>
                    </a>
                @endif

                @if(filled($contact->address))
                    <div class="flex gap-4 rounded-2xl border border-zinc-100 bg-white p-5 shadow-sm ring-1 ring-zinc-100">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200/80">
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1 text-start">
                            <span class="block text-xs font-semibold uppercase tracking-wide text-zinc-500">العنوان</span>
                            <p class="mt-1 text-base leading-relaxed text-zinc-800 whitespace-pre-line">{{ $contact->address }}</p>
                        </div>
                    </div>
                @endif

                @if(! filled($contact->phone) && ! filled($contact->email) && ! filled($contact->address))
                    <div class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/80 px-6 py-10 text-center text-sm text-zinc-600">
                        لم يتم تعبئة بيانات الاتصال بعد. يرجى إدخالها من لوحة التحكم → المحتوى → اتصل بنا.
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-inner ring-1 ring-zinc-100">
                @if(filled($contact->map_embed_html))
                    <div class="contact-map-embed aspect-[4/3] w-full min-h-[280px] sm:aspect-[16/10] sm:min-h-[320px]">
                        {!! $contact->map_embed_html !!}
                    </div>
                @else
                    <div class="flex aspect-[4/3] min-h-[280px] flex-col items-center justify-center gap-2 bg-zinc-100 px-6 text-center sm:aspect-[16/10] sm:min-h-[320px]">
                        <svg class="h-12 w-12 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        <p class="text-sm font-medium text-zinc-600">خريطة غير مفعّلة</p>
                        <p class="max-w-xs text-xs text-zinc-500">أضف كود التضمين من Google Maps في لوحة التحكم.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .contact-map-embed iframe {
            width: 100%;
            height: 100%;
            min-height: 280px;
            border: 0;
            display: block;
        }
    </style>
</x-layouts.store>
