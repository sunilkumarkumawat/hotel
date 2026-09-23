@if ($paginator->hasPages())
    <div class="nv-pagination">
        <span>
            Showing <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ number_format($paginator->total()) }}</strong>
        </span>

        <div class="nv-pages">
            @if ($paginator->onFirstPage())
                <span class="nv-page is-disabled"><x-icon name="chevron-left" :size="14" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="nv-page" rel="prev" aria-label="Previous">
                    <x-icon name="chevron-left" :size="14" />
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="nv-page is-disabled">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="nv-page is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="nv-page">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="nv-page" rel="next" aria-label="Next">
                    <x-icon name="chevron-right" :size="14" />
                </a>
            @else
                <span class="nv-page is-disabled"><x-icon name="chevron-right" :size="14" /></span>
            @endif
        </div>
    </div>
@else
    <div class="nv-pagination">
        <span>Showing <strong>{{ $paginator->total() }}</strong> {{ Str::plural('result', $paginator->total()) }}</span>
    </div>
@endif
