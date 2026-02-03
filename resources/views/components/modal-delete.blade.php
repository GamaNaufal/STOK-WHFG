@props([
    'id' => 'deleteModal',
    'title' => 'Hapus Data',
    'message' => 'Anda yakin ingin menghapus data ini?',
    'itemName' => '-'
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" id="{{ $id }}Label">
                    <i class="bi bi-exclamation-triangle"></i> {{ $title }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3" style="color: #374151;">{{ $message }}</p>
                <div class="p-3" style="background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 8px;">
                    <div class="fw-bold" id="{{ $id }}ItemName" style="color: #7f1d1d; font-size: 16px;">{{ $itemName }}</div>
                </div>
                <div class="text-muted small mt-3" style="color: #6b7280;">
                    <i class="bi bi-info-circle"></i> Tindakan ini tidak dapat dibatalkan. Data akan dihapus secara permanen.
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e5e7eb;">
                <button type="button" class="btn" style="background-color: #e5e7eb; color: #374151; border: none; padding: 8px 16px; border-radius: 6px;" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Batal
                </button>
                <form id="{{ $id }}Form" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn" style="background-color: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px;">
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
