@if ($paginator->hasPages())
<nav class="pager">
  @if ($paginator->onFirstPage())<span class="muted">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}">‹ Sebelumnya</a>@endif
  <span>Halaman {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
  @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Berikutnya ›</a>@else<span class="muted">›</span>@endif
</nav>
@endif
