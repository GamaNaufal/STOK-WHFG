# UI Redesign Progress

Repository checked: 19 October 2026 (Asia/Jakarta).
Baseline: branch `REDESIGN`, HEAD `d0e9467`. This records local implementation;
the exact revision deployed to production has not been verified.

## 1. Frontend architecture

- Laravel 12 server-rendered Blade; Bootstrap 5.3, Bootstrap Icons, vanilla JS,
  and page-specific jQuery/Select2. Charts use Chart.js and Highcharts.
- Main shell: `resources/views/shared/layouts/app.blade.php`; navigation and
  shell styles/scripts live under `resources/views/shared/layouts/partials/`.
- CSS order: Bootstrap → shell styles → page `@section('styles')` →
  `public/css/application-ui.css` → `public/css/apple-design.css`.
  Global overrides therefore load after page styles. No `@stack('styles')` slot;
  scripts support both `@section('scripts')` and `@push('scripts')`.
- Main shell loads public assets directly, including `public/js/alert-helper.js`.
  Vite/Tailwind 4 are configured in `vite.config.js`, `resources/css/app.css`,
  and `resources/js/app.js`, but this shell does not load them with `@vite`.
- Reusable UI: `resources/views/components/`; standalone login:
  `resources/views/shared/auth/login.blade.php`, `public/css/login-ui.css`.
- Dashboard: `resources/views/shared/dashboard.blade.php` for Operator, Sales,
  PPC, Admin Warehouse; `resources/views/operator/reports/operational.blade.php`
  for Admin Utama/Supervisi. Dashboard partials are in
  `resources/views/shared/partials/`; styles in `public/css/dashboard*.css`.

## 2. Implemented segments

| Segment | Current status and entry points |
| --- | --- |
| Shared foundation | Implemented: shell/sidebar/search, focus, responsive controls, restrained surfaces, login/profile feedback. Global coverage is a baseline, not a completed interaction audit of every page. See `docs/UI_UX_AUDIT.md`. |
| Dashboard | Implemented: role summaries, quick access, operational filters, stock activity charts, planned/actual delivery, mismatch summary, popovers, Current Handling/Active Pallet tables. On 19 October: Matching/Schedule Fulfillment tables use `resources/views/shared/partials/dashboard-order-table.blade.php` and `public/css/dashboard-stock-tables.css`. See `docs/DASHBOARD_SEGMENT_REVIEW.md` for earlier segments. Report-route presentation remains separate. |
| Lihat Stok | Implemented: four-mode toolbar/results, part/pallet details, edit/history/delete dialogs, single-modal focus restoration, retry/error/loading states, save/delete guards and reload feedback. Entry: `resources/views/shared/stock-view/index.blade.php`, its `partials/`, `public/css/stock-view-toolbar.css`, `public/css/stock-view-results.css`. See `docs/STOCK_VIEW_SEGMENT_REVIEW.md`. |
| Input Stok | Implemented: scoped responsive layout, stable scan fields, inline validation, duplicate-submit guards, lookup/reset/save error recovery. Entry: `resources/views/operator/stock-input/index.blade.php`, its `partials/`, `public/css/pages/operator-stock-input.css`, `public/js/pages/operator-stock-input.js`. See `docs/STOCK_INPUT_SEGMENT_REVIEW.md`. |
| Request/Approval Box Not Full | Implemented: grouped fields, capacity hints, inline validation, contextual confirmations, persistent feedback, submit guards, readable mobile approvals. Entry: `resources/views/operator/box-not-full/create.blade.php`, `resources/views/supervisor/approvals.blade.php`, `resources/views/shared/not-full-feedback.blade.php`, `public/css/not-full.css`, `public/js/pages/not-full.js`. See `docs/NOT_FULL_SEGMENT_REVIEW.md`. |

## 3. Visual direction and decisions

- Linear-inspired density and calm hierarchy are the project intent; existing
  implementation uses the system font, light neutral background, solid white
  content, subtle borders, restrained radii, teal actions and semantic status colors.
- Detailed tokens/interaction preferences: `docs/DESIGN_PREFERENCES.md`.
  Emil principles inform immediate feedback, focus, confirmation and recovery.
  Taste v2's public-site workflow does not apply to this operational dashboard;
  do not introduce marketing heroes, random art direction or decorative motion.
- Compact groups, readable tables, tabular numbers; scope page CSS and avoid scanner delays.
- Light theme; dashboard chart animation disabled. Review one bounded segment at a time.

## 4. Unfinished work and verification gaps

- Processing Time is implemented with `resources/views/shared/partials/dashboard-processing-table.blade.php`;
  dashboard-only definitions, displayed session count, sticky/keyboard-scroll table,
  right-aligned durations and explicit missing values. No duration rounding added.
- Warehouse Throughput is implemented in `resources/views/shared/partials/dashboard-throughput-table.blade.php`:
  daily count, explicit PCS direction, grouped numbers, sticky date/header and empty state.
- Scan Mismatch detail is implemented in `resources/views/shared/partials/dashboard-issues-table.blade.php`:
  explicit 50-row limit, wrapped codes, full multiline notes, neutral text statuses, sticky Order/header.
- Audit toolbar: `resources/views/shared/partials/dashboard-audit-filters.blade.php`,
  `public/css/dashboard-filters.css`; grouped fields, linked labels, 44px controls.
- Audit results: `resources/views/shared/partials/dashboard-audit-results.blade.php`;
  scoped summary counts, full multiline descriptions, sticky table and 44px pagination.
- Delivery Schedule header/toolbar: `resources/views/operator/delivery/partials/schedule-{header,filters}.blade.php`,
  `public/css/pages/delivery-schedule-toolbar.css`, `public/js/pages/delivery-schedule-filters.js`.
  Existing page had no list filter/API parameters. New client-only search (order/customer/part)
  and timing filter use already-rendered active rows; preserve priority order and all actions.
  Completed/history sections unaffected; no-JS retains all rows and hides filter controls.
- Active delivery results implemented in the same index and `public/css/pages/delivery-schedule-results.css`:
  sticky date/order and header, keyboard scroll, grouped FIFO-ready/required PCS, text sufficiency,
  wrapped part/customer data, 44px actions and responsive empty state. Existing action rules preserved.
- Dashboard audit AJAX repaired in `public/js/pages/dashboard-audit.js`: delegated
  Reset/pagination, loading/error/retry, 20s timeout, stale-response guards and focus recovery.
  Existing non-dashboard AJAX remains unchanged. Isolated browser interaction checks passed.
- Delivery, merge/move pallet, expired boxes, reports/audit, master/admin screens
  have shared styling but are not documented as completing the same detailed
  segment-by-segment interaction review as Stock View/Input/Not Full.
- Some legacy/dynamic labels still need accessibility review. Stock Input's
  delayed focus changes require physical scanner testing before alteration.
- Physical scanner/printer/touch devices, screen readers, large datasets and
  all network/session combinations remain unverified. Stock Input lookup races
  and successful print dialog output also need validation.

## 5. Recommended next segment

Continue with **Delivery Schedule completed orders / Redo window table** in
`resources/views/operator/delivery/index.blade.php`. Preserve eligibility, expiry, permissions
and Redo handlers; scope work to table presentation. Keep transactional modals separate.

## 6. Production safety

- Presentation changes must preserve form names, IDs used by scripts, payloads,
  endpoints, scanner focus/Enter behavior, audio feedback and server validation.
- Do not alter inventory/FIFO, picking locks, approval rules, transaction boundaries
  or quantity calculations as a side effect of redesign. Preserve permission-gated actions.
- Keep print/PDF/email templates separate from screen styling. Preserve CDN and
  asset loading order; check deployed asset caching when shipping CSS/JS changes.
- Use isolated fixtures and a verified testing database; `phpunit.xml` specifies
  SQLite `:memory:`. Never reset/seed the operational database or exercise real
  stock mutations for UI previews. No deployment is implied by this handoff.
- Run Laravel preview/tests sequentially on Windows: concurrent runs have
  previously collided on compiled Blade cache.

## 7. Relevant validation

- Earlier dashboard checks: 17 tests / 288 assertions passed (`UiReviewPreviewTest`,
  `OperationalReportServiceTest`, `FeatureAccessManagementTest`), SQLite `:memory:`.
- Dashboard fixtures cover empty/populated, large PCS, null/zero durations and long text;
  1440/768/390px checks passed for overflow, keyboard/sticky context and control sizes.
- Audit AJAX: 17 Laravel tests / 288 assertions and 7 Node regression tests passed
  (`tests/Frontend/dashboard-audit.test.js`): repeated Reset, failures/retry, invalid
  response, timeout, races, filter edits and focus. JS syntax/diff checks passed.
  Node uses DOM/network doubles. Browser fixture with actual JS passed submit,
  Reset twice, HTTP 500 → retry, pagination and focus restoration. Mobile error
  feedback at 390px has no overflow and a 44px retry button. No operational DB used.
- Audit results: 17 Laravel tests/288 assertions + 7 AJAX tests passed; escaping,
  fallback users/types and pagination query/fragment checked; responsive pagination 44px.
- Delivery toolbar/results: 36 Laravel tests/394 assertions (`UiReviewPreviewTest`,
  `FeatureAccessManagementTest`, `DeliveryFlowConsistencyTest`) + 4 frontend tests passed.
  Browser fixture 1440/768/390px: controls 44px, no toolbar overflow; combined search,
  timing, no-results and reset/focus verified. No transactions executed; JS/diff checks pass.
- Actual active-table Blade fixtures: long text, nine-digit PCS, empty and no-action permissions
  passed; 1440/768/390px no page/cell overflow, sticky/keyboard scroll and 44px actions verified.
  Final empty-state adjustment: `UiReviewPreviewTest` rerun passed (2 tests / 56 assertions).
- Browser checks use isolated Blade/CSS fixtures, not full-dashboard visual QA.
  No screenshots or physical-device checks. Temporary files/server removed; diff check passed.

### 19 October design decision

| Before | After | Why |
| --- | --- | --- |
| Two generic striped order tables | Shared table partial, clear title/context and displayed-order count | Consistent comparison with existing stock tables |
| Unformatted, left-aligned quantities | Grouped right-aligned PCS, tabular numerals, emphasized rate, text status chips | Faster comparison without changing values or relying on color alone |
| Wide tables with generic empty rows | Named keyboard-scroll regions, sticky header/Order column, explicit empty state | Preserve orientation and explain missing results |
| Audit labels unlinked; User ID displays names | Grouped linked fields, Petugas label, aligned Terapkan/Reset | Clearer filtering and keyboard use |
| Reset listener lost; failures silent; stale results overwrite | Delegation, live feedback/retry, cancellation and request sequence | Recover without losing results or newer selections |
| Audit summary scope unclear and description crowded | Explicit all-results summary, wider multiline description, scoped pagination | Distinguish filtered totals from current page |
| Delivery header oversized; no list filters | Compact header/count and scoped live search/timing toolbar | Find active schedules without changing order or loading data |
| Active delivery context lost on scroll; PCS meaning unclear | Sticky date/order, FIFO-ready/required label and Cukup/Belum cukup text | Retain orientation and distinguish available stock from shipped quantity |

Dashboard report-route anchors remain; delivery filtering is display-only. No backend/dependency changes.

## 8. Changes to preserve

- Current uncommitted work: this document, `public/css/dashboard-stock-tables.css`,
  `public/css/dashboard-filters.css`, `resources/views/operator/reports/operational.blade.php`,
  and new `resources/views/shared/partials/dashboard-{order-table,processing-table,
  throughput-table,issues-table,audit-filters,audit-results}.blade.php`, plus
  `public/js/pages/dashboard-audit.js` and `tests/Frontend/dashboard-audit.test.js`.
  Also preserve `resources/views/operator/delivery/index.blade.php`, both new schedule
  partials, delivery toolbar/results CSS and filter JS listed above, `tests/Frontend/delivery-schedule-filters.test.js`.
  No commits or deployment performed in these iterations.
