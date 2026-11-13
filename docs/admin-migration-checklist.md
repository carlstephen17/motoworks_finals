# Admin PHP-to-Symfony Migration Checklist

This is the working parity checklist for the Admin migration. The pure-PHP
implementation remains the source of truth. A page is not complete until its
UI, workflow, persistence, authorization, and regression checks have passed.

## Legacy inventory

### Screens and workflows

| Legacy page | Original behavior | Symfony counterpart | Current parity |
| --- | --- | --- | --- |
| [`admin/admin_index.php`](../admin/admin_index.php) | Dashboard counts for products, users, orders, service bookings, and promo claims; statistic cards link to related management pages. | [`AdminController::dashboard`](../src/Controller/AdminController.php), [`dashboard.html.twig`](../templates/admin/dashboard.html.twig) | Three legacy sections and their original card counts are retained with database-backed values. Admin page styling is isolated from storefront spacing/background styles; full visual comparison is still pending. |
| [`admin/admin_users.php`](../admin/admin_users.php) | All users; client-side search and role filtering; expandable create form; view/edit/delete modals; customer, staff, and admin roles. Blocks self-demotion/deletion and rejects duplicate username/email. | `/admin/users`, `/admin/users/new`, `/admin/users/{id}/edit`, `/admin/users/{id}/delete`; [`users.html.twig`](../templates/admin/users.html.twig) | Inline expandable Create form, client-side filters, details dialog, responsive Edit dialog, hashed-password flow, validation, and protected deletion are wired. Pixel-level comparison and complete create/update/delete browser verification remain pending. |
| [`admin/admin_products.php`](../admin/admin_products.php) | Search/status filters; view/edit/delete; add/edit modals with image, price, stock, and active state. Products with order history are deactivated; otherwise deleted with their image. | `/admin/products`; [`products.html.twig`](../templates/admin/products.html.twig) | Header Add action, view/edit dialogs, filters, uploads, CRUD, and history-aware deletion exist. Stock is edited in the product form; no separate stock-update action is shown. Exact visual and confirmation behavior remain to verify. |
| [`admin/admin_services.php`](../admin/admin_services.php) | Search/status filters update table rows without a full reload; view/add/edit modals; image upload; permanent delete and image cleanup. | `/admin/services`; [`services.html.twig`](../templates/admin/services.html.twig) | Header Add action, CRUD, filters, uploads, and dialogs exist. Search/status currently filter the already-rendered table; deletion deactivates services with booking history. |
| [`admin/admin_orders.php`](../admin/admin_orders.php) | Search, order/payment status and date filters; 50-row pagination; grouped order details; view/edit/status controls; delete. Cancellation restores stock, reactivation reserves it, and deletion removes order lines/header after restoring pending stock. | `/admin/orders`, `/admin/orders/{id}/status`, `/admin/orders/{id}/payment/verify`, `/admin/orders/{id}/delete`; [`orders.html.twig`](../templates/admin/orders.html.twig), [`_group_list.html.twig`](../templates/orders/_group_list.html.twig) | Grouped listing, summary cards, filters, pagination, status, payment verification, and stock-aware status updates exist. View/Edit now open a per-order dialog containing details and authorized status/payment actions. Delete semantics and full page-level visual parity remain unverified. |
| [`admin/admin_appointment.php`](../admin/admin_appointment.php) | Counts, search/status filters with AJAX table refresh, 14-column joined table, view/create/edit modals, status mapping (Confirmed→Pending, Rejected→Cancelled); completed bookings cannot be edited. | `/admin/appointments` and CRUD/status routes; [`appointments.html.twig`](../templates/admin/appointments.html.twig) | Four summary cards, a legacy-ordered 14-column table, and View/Create/Edit dialogs are present. Create/Edit post through Symfony forms and update the row without a reload; status is part of Edit rather than a separate row-level control. Search/status filtering still operates on the rendered rows, and full visual parity is pending. |
| [`admin/admin_promos.php`](../admin/admin_promos.php) | Client-side search/status filters; add/edit form modal; view modal; status update; delete promo while retaining claims. | `/admin/promos`, create/edit, and delete routes; [`promos.html.twig`](../templates/admin/promos.html.twig) | Client-side filters, details, and create/edit modals remain. Create and Edit save asynchronously, update the row without a reload, and surface validation failures. Active/Inactive is changed only in Edit. Deletion removes the promo and retains claim history. Full visual and workflow parity is still unverified. |
| [`admin/admin_promo_history.php`](../admin/admin_promo_history.php) | Newest-first table; client-side search/status filters; view/edit/delete; edit changes bundle slug and status only; Completed claims cannot be edited. Guest claims remain visible. | `/admin/promos?history=1` and claim routes; [`_promo_history_modal.html.twig`](../templates/admin/_promo_history_modal.html.twig) | Promotion History opens in a centered modal from the Promotions page and sidebar. The newest-first table, filters, badges, View/Edit/Delete controls, and claim details dialog are present. Completed/cancellation-pending edits are hidden; field-level parity and browser verification remain pending. |

### Shared source files, handlers, and assets

- Shared authorization/session/CSRF and database access: [`includes/auth.php`](../includes/auth.php), [`includes/config.php`](../includes/config.php).
- Shared order workflows: [`includes/orders.php`](../includes/orders.php), [`includes/order_deletion.php`](../includes/order_deletion.php).
- Upload validation/storage: [`admin/includes/upload_helpers.php`](../admin/includes/upload_helpers.php); product and service images are stored under `uploads/`.
- Action handlers: `admin/actions/admin_users_{create,update,delete}.php`, `admin/actions/admin_product_{create,update,delete}.php`, `admin/actions/admin_service_{create,update,delete}.php`, `admin/actions/admin_promo_{create,update,delete,status_update}.php`, `admin/actions/admin_promo_history_{update,delete}.php`, `admin/actions/admin_order_{update,delete}.php`, and `admin/actions/admin_appointment_{create,update,delete}.php`.
- `admin/actions/admin_stock_update.php` exists but is not called by a legacy page.
- Shared CSS: `admin/css/admin_layout.css`, `admin/css/admin_products.css`, and `admin/css/admin_dashboard.css`. The pages use Font Awesome 6.5.1; product/service and order images use stored image paths. Page scripts are inline; `admin/js/admin_products.js` is present but not loaded by the inspected screens.
- No dedicated legacy Reports screen/route was found in the Admin navigation or screen inventory. Do not add a new reports workflow unless another legacy source proves it exists.

## Migration and verification checklist

- [x] Inventory the eight legacy Admin screens, shared dependencies, action-handler families, and referenced asset types.
- [x] Map the current Symfony Admin routes, templates, forms, security rule, and database-backed dashboard.
- [ ] Rebuild each Symfony screen against its legacy page structure, styles, icons, and responsive behavior.
- [ ] Match each legacy action and modal to a Symfony route/service and preserve its database semantics.
- [ ] Verify user/staff create, update, and delete behavior, including self-protection and duplicate checks.
- [ ] Verify product/service image upload, replacement, deletion, and history constraints.
- [ ] Verify order filters, pagination, status/stock transitions, payment references, and true deletion semantics.
- [ ] Verify appointment joins, display-status mapping, filters, and completed-record restrictions.
- [ ] Verify promotion CRUD and promo-history search, status, guest records, edit restrictions, and deletion.
- [ ] Verify every confirmation, Cancel/No path, 3-second Undo, notification, duplicate-submit guard, and post-action UI refresh.
- [ ] Verify role access, CSRF, validation, persistence, routes, assets, and desktop/mobile behavior.
- [ ] Remove a legacy file only after its Symfony replacement and all dependencies are verified; no legacy files have been removed for this migration.

## Baseline findings

- Symfony Admin access is protected by `ROLE_ADMIN`; Staff/Customer denial is covered by controller tests.
- Current Admin route inventory is 34 routes, with matching controller coverage for the major CRUD areas.
- The focused Admin controller suite passes (24 tests, 260 assertions), including database-to-UI Orders and Appointments statistic checks, Promo status transitions and modal creation, appointment create/edit persistence, and order detail actions. It does not establish full page-by-page visual or workflow parity.
- The legacy Admin source is retained. The final cleanup gate is intentionally unchecked until all page-specific checks above pass.
