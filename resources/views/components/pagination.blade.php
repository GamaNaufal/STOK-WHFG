@props(['paginator'])

@if ($paginator->hasPages())
    <div class="p-4" style="background-color: #f8f9fa; border-top: 1px solid #e5e7eb;">
        <nav aria-label="Pagination Navigation" class="d-flex justify-content-between align-items-center">
            <!-- Previous Button -->
            <div>
                @if ($paginator->onFirstPage())
                    <button class="btn btn-sm" style="background-color: #e5e7eb; color: #9ca3af; border: none; padding: 8px 16px; border-radius: 8px; cursor: not-allowed;" disabled>
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; padding: 8px 16px; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#249E94'" onmouseout="this.style.backgroundColor='#0C7779'">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                @endif
            </div>

            <!-- Page Info -->
            <div class="text-center">
                <small class="text-muted d-block" style="font-size: 13px;">
                    <strong>Halaman {{ $paginator->currentPage() }}</strong> dari <strong>{{ $paginator->lastPage() }}</strong>
                </small>
                <small class="text-muted d-block" style="font-size: 12px;">
                    Menampilkan <strong>{{ $paginator->count() }}</strong> dari <strong>{{ $paginator->total() }}</strong> hasil
                </small>
            </div>

            <!-- Next Button -->
            <div>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; padding: 8px 16px; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#249E94'" onmouseout="this.style.backgroundColor='#0C7779'">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                @else
                    <button class="btn btn-sm" style="background-color: #e5e7eb; color: #9ca3af; border: none; padding: 8px 16px; border-radius: 8px; cursor: not-allowed;" disabled>
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                @endif
            </div>
        </nav>

        <!-- Page Number Links -->
        @if($paginator->lastPage() > 1)
        <div class="mt-3 d-flex justify-content-center gap-2">
            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; min-width: 40px; padding: 6px 10px; border-radius: 6px;">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $url }}" class="btn btn-sm" style="background-color: #f3f4f6; color: #0C7779; border: 1px solid #e5e7eb; min-width: 40px; padding: 6px 10px; border-radius: 6px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                        {{ $page }}
                    </a>
                @endif
            @endforeach
        </div>
        @endif
    </div>
@endif
