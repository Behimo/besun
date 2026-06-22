@props(['items' => []])

@if (count($items) > 1)
<nav aria-label="مسیر صفحه" class="mb-8">
    <ol class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        @foreach ($items as $i => $item)
            <li class="flex items-center gap-2">
                @if ($i > 0)
                    <span aria-hidden="true">/</span>
                @endif
                @if (!empty($item['url']) && $i < count($items) - 1)
                    <a href="{{ $item['url'] }}" class="transition hover:text-bisan-orange">{{ $item['name'] }}</a>
                @else
                    <span class="text-slate-300">{{ $item['name'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
