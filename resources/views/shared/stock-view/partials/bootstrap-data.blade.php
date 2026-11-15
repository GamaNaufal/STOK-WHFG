<script id="stockViewBootstrap" type="application/json">
{!! json_encode([
    'canEditBox' => Auth::user()->canAccess('stock_edit'),
    'canDeleteStock' => Auth::user()->canAccess('stock_delete'),
    'allParts' => $allParts,
    'masterParts' => $masterPartNumbers,
    'masterPartCapacities' => $masterPartCapacities ?? [],
    'allPallets' => $allPallets,
    'allBoxNumbers' => $allBoxNumbers,
    'allLocations' => $allLocations,
    'allSearchTerms' => $allSearchTerms,
    'search' => $search,
    'directBoxTarget' => $directBoxTarget,
    'viewMode' => $viewMode,
    'csrfToken' => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
