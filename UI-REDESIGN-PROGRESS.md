# UI Redesign Progress

Repository checked: 21 October 2026 (Asia/Jakarta). Baseline: branch `REDESIGN`, HEAD `ae67525`; local implementation only, production revision unverified.

## 1. Frontend architecture

- Laravel 12 server-rendered Blade; Bootstrap 5.3, Bootstrap Icons, vanilla JS, and page-specific jQuery/Select2. Charts use Chart.js and Highcharts.
- Main shell: `resources/views/shared/layouts/app.blade.php`; navigation/styles/scripts: `resources/views/shared/layouts/partials/`. CSS order: Bootstrap → shell → page styles → `public/css/application-ui.css` → `public/css/apple-design.css`; global overrides load last. Scripts support sections/stacks, styles do not have `@stack`.
- Main shell loads public assets directly, including `public/js/alert-helper.js`; Vite/Tailwind 4 are configured but this shell does not load them with `@vite`.
- Reusable UI: `resources/views/components/`; standalone login: `resources/views/shared/auth/login.blade.php`, `public/css/login-ui.css`.
- Dashboard: `resources/views/shared/dashboard.blade.php` for Operator, Sales, PPC, Admin Warehouse; `resources/views/operator/reports/operational.blade.php` for Admin Utama/Supervisi. Partials: `resources/views/shared/partials/`; styles: `public/css/dashboard*.css`.

## 2. Implemented segments

| Segment | Current status and entry points |
| --- | --- |
| Shared foundation | Implemented: shell/sidebar/search, focus, responsive controls, restrained surfaces and login. `/profile` now has grouped identity/security fields, inline validation, optional-password guidance and responsive actions in `resources/views/profile/edit.blade.php` and `public/css/pages/profile.css`. Global coverage is a baseline, not a completed interaction audit of every page. See `docs/UI_UX_AUDIT.md`. |
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
- Compact groups, readable tables, tabular numbers; scoped CSS, light theme, disabled chart animation and no scanner delays. Review one bounded segment at a time.

## 4. Unfinished work and verification gaps

- Dashboard processing table in `resources/views/shared/partials/dashboard-processing-table.blade.php` and
  `public/css/dashboard-stock-tables.css`: session, customer, session creator, date/time and duration hierarchy;
  durations display as minutes:seconds (e.g. `00:17`), with zero distinct from missing data and bounded cells.
  `OperationalReportService` eager-loads names; metrics, anchor, roles and export mapping unchanged.
- Dashboard throughput table in `resources/views/shared/partials/dashboard-throughput-table.blade.php`: exact inbound/outbound PCS with bars scaled to the largest daily flow, active-day count and zero-day label. Totals/date grouping unchanged.
- Dashboard scan issues table in `resources/views/shared/partials/dashboard-issues-table.blade.php`: readable reason plus audit code, textual status, full scan/notes and split date/time. `#scan-issues` targets this table (not Processing Time) on dashboard/report branches. 50-row scope, filters and export mapping unchanged; audit remains.
- Dashboard audit filters in `resources/views/shared/partials/dashboard-audit-filters.blade.php` and `public/css/dashboard-filters.css`: active-filter count/chips, clear applied-vs-pending guidance and responsive hierarchy. Submit carries period/date/group/fulfillment context; Reset clears only audit filters and pagination. Audit query, roles and report route remain unchanged.
- Standalone audit trail `/audit-trail`: `resources/views/supervisor/audit/index.blade.php`, `partials/detail.blade.php` and `public/css/pages/audit-trail.css` give header, date-scoped metrics, filters/results and detail modal a responsive hierarchy. On 21 Oct, filters/presenter gained Handle, Box Not Full, picking/issue, master/admin and profile audit types; `AuditService::record` makes new entries transactional. Delivery Order mutation audit remains excluded; legacy `AuditService::log` remains best-effort. Export uses the same generic type/action query; the separate Operational Report Blade branch has no registered GET route.
- Stock Input report: `resources/views/operator/reports/stock-input.blade.php` and `public/css/pages/report-stock-input.css` have a restrained header, responsive filters, whole-history summary cards and a results table with sticky record/date, wrapped part/box audit labels, filtered count and contextual empty state. `ReportController::stockInputReport` paginates 15 rows; scoped Bootstrap 5 pagination avoids oversized Tailwind arrows and retains filters. Export remains unpaginated. Routes and roles are unchanged; cards cover all history.
- Withdrawal report: `resources/views/operator/reports/withdrawal.blade.php` and `public/css/pages/report-withdrawal.css` now cover header, whole-history summary cards, five labeled filters, sticky-date result table, full notes, contextual empty state and Bootstrap pagination. `reversed` is labeled Dikembalikan/Redo throughout the UI. `ReportController::withdrawalReport` paginates 15 rows. Excel still applies only date/status (not part/operator); this difference is disclosed in UI. Routes, roles and status queries remain unchanged.
- Delivery Schedule header/toolbar: `resources/views/operator/delivery/partials/schedule-{header,filters}.blade.php`,
  `public/css/pages/delivery-schedule-toolbar.css`, `public/js/pages/delivery-schedule-filters.js`.
  Client-only search/timing filter uses rendered active rows; priority order/actions remain. No-JS shows all rows.
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
  and sticky picklist ID. On 21 Oct, the list also keeps its compact action column visible beside Order on phones during horizontal scroll, with full accessible button names. Start/resume, Enter/focus, timeout, cancel and permissions are unchanged.
- Picking Verification rejects wrong/empty/unlisted or withdrawn/expired box scans
  without counting them, creating an issue or blocking the session; the next scan can
  proceed. Final Delivery still creates a pending issue and blocks on wrong scans,
  including short, nonnumeric, empty and long codes, until admin approval. Expired
  Handle renewal now has explicit scan guidance in both stages and a verification hint.
- Scan Issues management uses `resources/views/operator/delivery/scan-issues.blade.php`
  and `public/css/pages/delivery-scan-issues.css`: pending/history hierarchy, visible
  reason/code, linked notes, 44px approval and sticky Order. Confirmation clarifies that
  approval unlocks the session without counting the bad scan; routes/statuses are unchanged.
- Delivery lock management uses `resources/views/operator/delivery/lock-management.blade.php`
  and `public/css/pages/delivery-lock-management.css`: Admin-only summary, clear status/counts,
  sticky session/order header and cells, labeled reason, terminate confirmation and empty state.
- Delivery Assign page hierarchy is updated in `resources/views/operator/delivery-assign/index.blade.php`
  and `public/css/pages/delivery-assign.css`: numbered stages, readable rows, responsive controls.
  Its JS confirms removal/reset, restores drafts, previews box IDs and explains per-part overage;
  only confirmation raises request and retries assign. New pallets use `PalletNumberService`; legacy manual payload remains; location claims and server revalidation stay atomic.
- Move Pallet uses `resources/views/operator/move-pallet/index.blade.php` and `public/css/pages/move-pallet.css`:
  numbered stages, search safeguards, route confirmation, and a 20-row audit table with clear origin/destination,
  operator, date/time, empty state, and aligned mobile scroll. Scan/API unchanged.
- Merge Pallet uses `resources/views/operator/merge/index.blade.php` and `public/css/pages/merge-pallet.css`:
  source/selection, location chooser, impact preview and audit history table completed; typed codes require a listed choice, stale results ignored, and source removal clears its location. History shows up to 20 records.
- Split backend/schema: MySQL `db_stock` lacked enum value `partial`, causing Split to fail when updating its parent. `database/migrations/2026_10_20_000001_add_partial_to_existing_delivery_order_status.php`
  adds the missing value to existing MySQL enums; applied locally on 20 October. No Split rule changed.
- `/expired-box`: `resources/views/warehouse/expired-box/index.blade.php`, `public/css/pages/expired-box.css`, `public/js/pages/expired-box.js` give status counts, FIFO/Handle guidance, current-row search, scrollable tables, elapsed-Handle label, contextual location choice, confirmation and 15-row Handle history. Handle now also records one transactional `expired_box` Audit Trail entry per successful activation; FIFO/role rules unchanged.
- Master Location complete: `resources/views/admin/locations/{index,edit}.blade.php` and `public/css/pages/master-locations.css` cover page headers, create/edit forms, validation feedback, occupancy context, labeled table actions, empty state and bounded pagination. Occupied edit is read-only; occupied deletion is disabled in UI while server guards remain. Other Delivery, remaining reports/master screens, devices, screen readers and large datasets remain unverified.
- Master Part & Qty complete: `resources/views/operator/part-settings/{index,create,edit}.blade.php`, `public/css/pages/master-parts.css`, `public/js/pages/master-parts.js` cover header, capped global search with loading/error/reset, consistent PCS/box units, bounded pagination, create/edit guidance and deletion context. Existing 10-row pages, 50-result search API, validation, referenced-part guards, routes and roles remain.
- User Management complete: `resources/views/admin/users/{index,create,edit}.blade.php`, `public/css/pages/user-management.css`, `public/js/pages/user-management-forms.js`, and `UserController::index` cover account totals, server-side name/email search, role/status filters, 15-row pagination, responsive table, labeled create/edit fields, role/status guidance, matching password feedback and contextual confirmations. Own-account deactivation is unavailable in the list; routes, server validation, role options, activation and audit behavior remain.
- Hak Akses complete: `resources/views/admin/access/index.blade.php`, `public/css/pages/access-management.css`, and `public/js/pages/feature-access.js` show grouped permissions, selected/group counts, locked Admin-only features, dependency notes, unsaved state and confirmation before role-wide save (including empty selection). Existing role switch, dependency sync, payload, server validation and audit semantics remain.
- Sales Delivery Order complete: `resources/views/operator/delivery/{create,edit}.blade.php` share `partials/sales-order-fields.blade.php`, `public/css/pages/sales-delivery-order.css`, and `public/js/pages/sales-delivery-order.js`. Forms group customer/date and part/PCS, show PPC correction notes, preserve input after errors, flag duplicate parts, summarize PCS and confirm submission. The Sales history table now groups order/date, customer/status and actions; shows up to 50 own orders with visible correction/rejection notes, sticky Order when scrolling, and item details for all statuses. Payload, ownership, correction-only edits and PPC flow remain.
- PPC approval page complete: `resources/views/operator/delivery/approvals.blade.php`, `public/css/pages/ppc-approval-queue.css`, and `public/js/pages/ppc-approval-decision.js` cover pending count, per-part ready/required and total stock, three decisions, note guidance and approval-impact feedback (checking/safe/affected/failure with retry and stale-response guard). The history table shows up to 15 updated orders with sticky Order, textual status, full notes and item details in a stable collapse row. Decision payload, endpoint, stock calculations, history query, server rules and PPC access remain.
- Pallet business guard (20 Oct): assigned/not-full boxes and `pending` pick sessions allow Move/Merge.
  Any attached box in `scanning`, `blocked`, or `approved` blocks Move/Merge and additions
  through Stock Input/Box Not Full, plus sibling assignment, Stock View mutation and expired Handle. Merge retargets pending Box Not Full requests; assignment and PCS stay intact.

## 5. Recommended next segment

Next, check the active Picking Verification scan screen at phone width and by keyboard, then fix one concrete usability gap. The registered `/delivery-stock/{id}/fulfill` view has no link from the active flow; FIFO/picklist uses the Schedule modal. `resources/views/operator/stock-withdrawal/index.blade.php` has no GET route or active navigation link; the Withdrawal report is `/reports/withdrawal`.

## 6. Production safety

- Presentation changes must preserve form names, script IDs, payloads, endpoints, scanner focus/Enter behavior, audio feedback and server validation.
- Do not alter inventory/FIFO, picking locks, approval rules, transaction boundaries or quantity calculations as a side effect of redesign. Preserve permission-gated actions.
- Keep print/PDF/email templates separate from screen styling. Preserve CDN and
  asset loading order; check deployed asset caching when shipping CSS/JS changes.
- Use isolated fixtures and a verified testing database; `phpunit.xml` specifies
  SQLite `:memory:`. Never reset/seed the operational database or exercise real
  stock mutations for UI previews. No deployment is implied by this handoff.
- Run Laravel preview/tests sequentially on Windows to avoid compiled Blade cache collisions.

## 7. Relevant validation

- Scan stages: prior 34 Laravel tests/264 assertions and 13 Node tests passed; current Picking Verification list 390px fixture kept Order/Aksi visible at maximum horizontal scroll and keyboard Tab reached the visible action. Render 1/26 and activity 13/157 Laravel tests passed; wrong verification input still does not block, while final scan blocks pending approval. Device check remains open.
- Delivery lock management: 15 Laravel tests/287 assertions passed; 390px browser fixture
  kept header/body aligned at 843px scroll.
- Delivery Assign: prior 18 Laravel tests/134 assertions; current focused 15/70 and 4 Node
  tests passed. Syntax/diff checks passed; no live stock mutation or device check.
- Move/Merge UI: 4/3 Node interaction tests passed; Move history 8 Laravel/63 assertions and Merge history 13 Laravel/120 assertions passed. Browser/device visual check untested.
- Pallet delivery guard: 60 focused Laravel tests/350 assertions passed across Move, Merge,
  Stock Input, Delivery Assign and Box Not Full; Stock View sibling guard also passed.
- Expired Box/verification: 20 focused Laravel tests/230 assertions and 3 Node interaction tests passed after page redesign; Handle audit tests 12/133 passed on 21 Oct. Earlier Delivery Flow Consistency 22/125 passed. No browser/device visual or live scanner check.
- Scan Issues: 24 Laravel tests/181 assertions passed with pending/history rendering, approval/unblock and permission checks; responsive layout reviewed in CSS only.
- Dashboard processing/throughput/issues/audit filters: prior `UiReviewPreviewTest` 8/109 and report/export sanity 3/24 passed. Standalone Audit Trail: 10 audit/detail/auth tests/89 assertions passed; full Laravel suite on 21 Oct passed 233 tests/1,723 assertions after broader audit coverage. Master Location and Master Part & Qty: 42 focused Laravel tests/447 assertions plus 2 Node search/delete tests passed; browser/device visual check remains open.
- Stock Input/Withdrawal reports: 16 focused Laravel tests/157 assertions passed; after Withdrawal wording revision, 8 filter/auth/export tests/48 assertions passed. Coverage includes 15+1 pagination, filter retention and full notes; browser/device visual check remains open.
- User Management list/forms: 28 focused Laravel tests/409 assertions passed (rendering, filters, pagination, access and audit); JS syntax and diff checks passed. Browser/device visual check remains open.
- Hak Akses: 22 Laravel tests/364 assertions and 2 Node interaction tests passed (dependency, empty-save warning, dirty navigation). Browser/device visual check remains open.
- Sales Delivery Order forms/history: 26 focused Laravel tests/302 assertions after history update; prior 2 Node interaction tests passed (item controls), JS syntax passed. Coverage includes correction item details, ownership, PPC notes, Sales/PPC flow and permissions. Browser/device visual check remains open.
- PPC approval page: 34 focused Laravel tests/438 assertions and 3 Node modal tests passed (stock context, decisions, notes/items in history, impact gating/retry, stale responses); JS syntax and Blade compilation passed. Profile: render test 1/26 and update/audit test 1/10 passed; browser/device visual check remains open.

## 8. Changes to preserve

- Earlier Audit Trail/Stock Input edits are in HEAD `ae67525`; preserve uncommitted Hak Akses, Sales Delivery Order, PPC queue, Profile, Picking Verification list Blade/CSS, related tests and this handoff. The status migration ran only on local `db_stock`; production remains unverified.
