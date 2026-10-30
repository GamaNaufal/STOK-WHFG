# Local UI dependencies

These are pinned copies of the UI dependencies previously loaded from public
CDNs. Versions and execution order are preserved. SweetAlert2's former `@11`
URL resolved to 11.26.25 when copied on 29 October 2026. Both jQuery versions
are retained for their existing pages; this change is not a library upgrade.

`ui-manifest.json` records the npm CDN source URL and SHA-256 for every asset
and license. Keep the MIT license files alongside the distributions. Bootstrap
Icons retains its relative font directory. Include this entire `public/vendor`
directory in deployments; no download, npm package installation, or extra server
service is needed at runtime. The test suite checks all hashes and file paths.

The operational reporting page's Highcharts and Chart.js dependencies remain
as configured; they are outside the common navigation assets optimized here.
