@if ($paginator->hasPages())
    <nav class="pagination simple" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="page-link disabled" aria-disabled="true">{!! __('pagination.previous') !!}</span>
            @else
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{!! __('pagination.previous') !!}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">{!! __('pagination.next') !!}</a>
            @else
                <span class="page-link disabled" aria-disabled="true">{!! __('pagination.next') !!}</span>
            @endif
        </div>
    </nav>
@endif
