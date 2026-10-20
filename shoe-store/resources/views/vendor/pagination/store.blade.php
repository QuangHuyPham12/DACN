@if ($paginator->hasPages())
<nav class="pagination" aria-label="Phân trang">
<span class="muted">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} kết quả</span>
<div class="pagination-links">
@if($paginator->onFirstPage())<span class="page muted">Trước</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Trước</a>@endif
@foreach($elements as $element)
@if(is_string($element))<span class="page">{{ $element }}</span>@else
@foreach($element as $page => $url)
@if($page == $paginator->currentPage())<span class="page current" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
@endforeach
@endif
@endforeach
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Sau</a>@else<span class="page muted">Sau</span>@endif
</div></nav>
@endif
