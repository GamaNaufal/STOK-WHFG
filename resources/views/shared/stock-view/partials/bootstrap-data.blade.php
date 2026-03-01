<script id="stockViewBootstrap" type="application/json">
{!! json_encode([
    'currentUserRole' => Auth::user()->role ?? null,
    'allParts' => $groupedByPart->pluck('part_number')->values(),
    'masterParts' => $masterPartNumbers,
    'allPallets' => $groupedByPallet->pluck('pallet_number')->values(),
    'viewMode' => $viewMode,
    'csrfToken' => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
