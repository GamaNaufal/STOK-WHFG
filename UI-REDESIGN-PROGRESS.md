# UI Redesign Progress

Repository checked: 20 October 2026 (Asia/Jakarta).
Baseline: branch `REDESIGN`, HEAD `c495070`. This records local implementation;
the exact revision deployed to production has not been verified.

## 1. Frontend architecture

- Laravel 12 server-rendered Blade; Bootstrap 5.3, Bootstrap Icons, vanilla JS,
  and page-specific jQuery/Select2. Charts use Chart.js and Highcharts.
- Main shell: `resources/views/shared/layouts/app.blade.php`; navigation and
  shell styles/scripts live under `resources/views/shared/layouts/partials/`.
- CSS order: Bootstrap → shell styles → page `@section('styles')` →
  `public/css/application-ui.css` → `public/css/apple-design.css`.
  Global overrides load after page styles; no `@stack('styles')`. Scripts support sections/stacks.
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

- Dashboard processing, throughput and mismatch tables are implemented in
  `resources/views/shared/partials/dashboard-{processing,throughput,issues}-table.blade.php`.
  Audit filters/results live in `resources/views/shared/partials/dashboard-audit-{filters,results}.blade.php`;
  they retain scoped counts, keyboard-scrollable tables and 44px controls.
- Delivery Schedule header/toolbar: `resources/views/operator/delivery/partials/schedule-{header,filters}.blade.php`,
  `public/css/pages/delivery-schedule-toolbar.css`, `public/js/pages/delivery-schedule-filters.js`.
  Existing page had no list filter/API parameters. New client-only search (order/customer/part)
  and timing filter use already-rendered active rows; preserve priority order and all actions.
  Completed/history sections unaffected; no-JS retains all rows and hides filter controls.
- Active delivery results implemented in the same index and `public/css/pages/delivery-schedule-results.css`:
  sticky date/order and header, keyboard scroll, grouped FIFO-ready/required PCS, text sufficiency,
  wrapped part/customer data, 44px actions and responsive empty state. Post-split action buttons
  keep readable labels and wrap as units. Hapus is hidden for split children and parents with
  active children; `DeliveryOrderController::destroy` rejects both direct-route deletions.
- Completed/Redo table implemented in the same index and `public/css/pages/delivery-completed.css`:
  explicit 20-row scope/count, date/time hierarchy, emphasized deadline, sticky Order/header,
  keyboard scroll and 44px Redo buttons. Empty collection still hides the section; guards unchanged.
- Delivery history table implemented in the same index and `public/css/pages/delivery-history.css`:
  combined-source scope/count, accurate event-time label, grouped PCS, textual status chips,
  sticky Order/header and keyboard scroll. Empty collection still hides the section.
- Redo location dialogs use `public/css/pages/delivery-redo-dialog.css`: shared hierarchy,
  explicit single-location guidance, conflict notice, 44px choices, visible focus/loading/error.
  Multiple-pallet required and single-pallet conditional selection remain unchanged.
- Split Delivery modal uses `public/css/pages/delivery-split-modal.css`: order/effect context,
  selected-part count, scoped scroll/sticky part, labeled PCS bounds and responsive controls.
  Existing payload/validation retained; impossible/empty selections disable Save.
- FIFO recommendation modal uses `public/css/pages/delivery-fifo-modal.css`: clear order
  context, per-part PCS hierarchy, keyboard-scrollable box table and stable Process footer.
  FIFO preview, blocking, start-pick endpoints and payloads are unchanged.
- Picklist handoff modal uses `public/css/pages/delivery-picklist-modal.css`: active session
  context, two-step print/PDF then scan guidance, and distinct 44px actions that stack on phones.
  Existing preview/PDF fallback URL, new-tab behavior, scan URL and start-pick flow remain intact.
- Final delivery scan screen uses `resources/views/operator/delivery/scan.blade.php` and
  `public/css/pages/delivery-scan.css`: scanner-first layout, session progress, 44px actions,
  sticky ID box in scrollable picklist. IDs, Enter/focus, audio, routes and permissions are unchanged.
- Picking Verification list/scan use `resources/views/operator/delivery/{picking-verification,verify-scan}.blade.php`
  and `public/css/pages/{picking-verification-list,delivery-verification}.css`: the list has
  scoped counts, contextual status, 44px actions and sticky Order; scan has clear progress/timer
  and sticky picklist ID. Start/resume, Enter/focus, timeout, cancel and permissions are unchanged.
- Wrong scans in verification/final create a pending issue and block for unmatched short,
  nonnumeric, empty or absent codes and withdrawn/expired boxes. Both screens submit any
  input, stop queued scans on `session_status=blocked`, and require approval/reload.
  Issue codes cap at 255 characters; empty codes are recorded as `(kosong)`.
- Scan Issues management uses `resources/views/operator/delivery/scan-issues.blade.php`
  and `public/css/pages/delivery-scan-issues.css`: pending/history hierarchy, visible
  reason/code, linked notes, 44px approval and sticky Order. Confirmation clarifies that
  approval unlocks the session without counting the bad scan; routes/statuses are unchanged.
- Split backend/schema: MySQL `db_stock` lacked enum value `partial`, causing Split to fail when
  updating its parent. `database/migrations/2026_10_20_000001_add_partial_to_existing_delivery_order_status.php`
  adds the missing value to existing MySQL enums; applied locally on 20 October. No Split rule changed.
- Delivery, merge/move pallet, expired boxes, reports/audit, master/admin screens
  have shared styling but are not documented as completing the same detailed
  segment-by-segment interaction review as Stock View/Input/Not Full.
- Physical scanner/printer/touch devices, screen readers, large datasets and
  all network/session combinations remain unverified. Stock Input lookup races
  and successful print dialog output also need validation.

## 5. Recommended next segment

Continue with **Delivery lock management** in `resources/views/operator/delivery/lock-management.blade.php`.
Preserve Admin-only access and the existing stop/unlock behavior.

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

- Final delivery scan: 45 Laravel tests/443 assertions; 1440/768/390px layout fixture passed.
- Wrong-scan repair: 55 focused Laravel tests/455 assertions and 8 Node tests passed;
  short/nonnumeric/empty/absent/long codes block in both scan stages, with approval recovery
  and queued-scan cancellation. No live scans or physical scanner test.
- Picking Verification layout: 34 Laravel tests/290 assertions and 12 Node tests passed;
  these cover blocked scan, cancellation, timer and Enter queue. Responsive layout was
  reviewed in CSS only; physical scanner, screen reader and device testing remain open.
- Picking Verification list: 14 Laravel tests/229 assertions passed (module render and
  verification flow); responsive styling reviewed in CSS, without live operational requests.
- Scan Issues: 24 Laravel tests/181 assertions passed with pending/history rendering,
  approval/unblock and permission checks; responsive layout reviewed in CSS only.

## 8. Changes to preserve

- Current uncommitted work: this document, `resources/views/operator/delivery/index.blade.php`,
  `resources/views/operator/delivery/{picking-verification,scan,scan-issues,verify-scan}.blade.php`, `public/css/pages/{delivery-scan,delivery-scan-issues,picking-verification-list}.css`,
  `app/Http/Controllers/{DeliveryOrder,DeliveryPick}Controller.php`,
  `tests/Feature/{DeliverySalesPpcWorkflowTest,DeliveryFlowConsistencyTest,PickingVerificationActivityTest,ExpiredBoxReactivationTest}.php`,
  `tests/Frontend/delivery-scan-blocking.test.js`,
  `public/css/pages/delivery-{schedule-results,fifo-modal,picklist-modal}.css`,
  new `public/css/pages/delivery-{completed,history,redo-dialog,split-modal,verification}.css`,
  `docs/AGENT_SYSTEM_MEMO.md` and the 20 October status migration. Preserve these files;
  the migration ran only on local `db_stock`, with no production deployment.
