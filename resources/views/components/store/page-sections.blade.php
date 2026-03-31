@props([
    'sections' => [],
])

@php
    $blocks = is_array($sections) ? $sections : [];
@endphp

<div class="mt-10 space-y-12">
    @foreach ($blocks as $block)
        @php
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];
        @endphp

        @continue(blank($type))

        @if ($type === 'hero')
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm">
                @if (! empty($data['image']))
                    <div class="aspect-[21/9] w-full overflow-hidden bg-zinc-100">
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($data['image']) }}"
                            alt=""
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                @endif
                <div class="px-6 py-8 sm:px-10">
                    @if (! empty($data['heading']))
                        <h2 class="text-xl font-bold text-zinc-900 sm:text-2xl">{{ $data['heading'] }}</h2>
                    @endif
                    @if (! empty($data['subheading']))
                        <p class="mt-2 text-zinc-600">{{ $data['subheading'] }}</p>
                    @endif
                    @if (filled($data['cta_url'] ?? null) && filled($data['cta_label'] ?? null))
                        <a
                            href="{{ $data['cta_url'] }}"
                            class="mt-6 inline-flex rounded-full bg-[#ff751f] px-5 py-2.5 text-sm font-semibold text-white hover:bg-orange-600"
                        >
                            {{ $data['cta_label'] }}
                        </a>
                    @endif
                </div>
            </section>
        @elseif ($type === 'content_block')
            <section class="prose prose-zinc max-w-none prose-headings:font-bold">
                {!! $data['body'] ?? '' !!}
            </section>
        @elseif ($type === 'image_block')
            <figure class="space-y-2">
                @if (! empty($data['image']))
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($data['image']) }}"
                        alt="{{ $data['alt'] ?? '' }}"
                        class="w-full rounded-xl border border-zinc-200 object-cover"
                        loading="lazy"
                    />
                @endif
                @if (! empty($data['caption']))
                    <figcaption class="text-center text-sm text-zinc-500">{{ $data['caption'] }}</figcaption>
                @endif
            </figure>
        @elseif ($type === 'gallery_block')
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($data['images'] ?? [] as $row)
                    @if (! empty($row['image']))
                        <figure class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50">
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($row['image']) }}"
                                alt=""
                                class="aspect-video w-full object-cover"
                                loading="lazy"
                            />
                            @if (! empty($row['caption']))
                                <figcaption class="px-3 py-2 text-center text-xs text-zinc-600">{{ $row['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @endif
                @endforeach
            </div>
        @elseif ($type === 'cta_block')
            @php
                $style = $data['style'] ?? 'primary';
                $btnClass = match ($style) {
                    'secondary' => 'bg-zinc-800 text-white hover:bg-zinc-900',
                    'outline' => 'border border-zinc-300 bg-white text-zinc-900 hover:bg-zinc-50',
                    default => 'bg-[#ff751f] text-white hover:bg-orange-600',
                };
            @endphp
            <section class="rounded-2xl border border-zinc-200 bg-zinc-50 px-6 py-8 text-center sm:px-10">
                @if (! empty($data['title']))
                    <h2 class="text-lg font-bold text-zinc-900 sm:text-xl">{{ $data['title'] }}</h2>
                @endif
                @if (! empty($data['body']))
                    <p class="mt-3 text-sm leading-relaxed text-zinc-600">{{ $data['body'] }}</p>
                @endif
                @if (filled($data['button_url'] ?? null) && filled($data['button_label'] ?? null))
                    <a
                        href="{{ $data['button_url'] }}"
                        class="mt-6 inline-flex rounded-full px-5 py-2.5 text-sm font-semibold {{ $btnClass }}"
                    >
                        {{ $data['button_label'] }}
                    </a>
                @endif
            </section>
        @endif
    @endforeach
</div>
