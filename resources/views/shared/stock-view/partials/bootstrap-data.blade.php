<script id="stockViewBootstrap" type="application/json">
{!! json_encode([
    'currentUserRole' => Auth::user()->role ?? null,
    'allParts' => $searchPartNumbers ?? collect(),
    'masterParts' => $masterPartNumbers,
    'allPallets' => $searchPalletNumbers ?? collect(),
    'allPalletIds' => $searchPalletIds ?? collect(),
    'allBoxIds' => $searchBoxIds ?? collect(),
    'viewMode' => $viewMode,
    'csrfToken' => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
