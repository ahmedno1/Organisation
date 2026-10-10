@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pagination-info">
            {!! __('Showing') !!}
            @if ($paginator->firstItem())
                <span>{{ $paginator->firstItem() }}</span>
                {!! __('to') !!}
                <span>{{ $paginator->lastItem() }}</span>
            @else
                {{ $paginator->count() }}
            @endif
            {!! __('of') !!}
            <span>{{ $paginator->total() }}</span>
            {!! __('results') !!}
        </div>

        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="page-link disabled" aria-disabled="true">{!! __('pagination.previous') !!}</span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{!! __('pagination.previous') !!}</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="page-link disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-link active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{!! __('pagination.next') !!}</a>
            @else
                <span class="page-link disabled" aria-disabled="true">{!! __('pagination.next') !!}</span>
            @endif
        </div>
    </nav>
@endif
