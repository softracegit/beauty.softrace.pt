@if(isset($paginator) && $paginator->hasPages())
  <div class="mt-3">
    {{ $paginator->links() }}
  </div>
@endif
