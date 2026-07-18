@if ($paginator->hasPages())
    <nav class="flex gap-1 wrap" style="margin-top: 18px;">
        @if ($paginator->onFirstPage())
            <span class="btn btn-light btn-sm" style="opacity:.5;">←</span>
        @else
            <a class="btn btn-light btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">←</a>
        @endif

        <span class="btn btn-light btn-sm">
            Halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-light btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">→</a>
        @else
            <span class="btn btn-light btn-sm" style="opacity:.5;">→</span>
        @endif
    </nav>
@endif
