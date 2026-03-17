<script id="stockViewBootstrap" type="application/json">
{!! json_encode([
    'currentUserRole' => Auth::user()->role ?? null,
    'allParts' => $allParts,
    'masterParts' => $masterPartNumbers,
    'allPallets' => $allPallets,
    'allBoxNumbers' => $allBoxNumbers,
    'allSearchTerms' => $allSearchTerms,
    'search' => $search,
    'directBoxTarget' => $directBoxTarget,
    'viewMode' => $viewMode,
    'csrfToken' => csrf_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
