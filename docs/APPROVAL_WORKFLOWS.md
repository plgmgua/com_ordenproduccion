# Internal approval workflows (Option B) — plan & status

**Status:** paused (no further approval-workflow features planned until this document is picked up again).

This document captures the original intent, what shipped, what is still open, and where to continue in code.

---

## Goal

Add an internal approval engine so sensitive actions (cotización confirmation, payment proof verification, timesheet approval, and optionally order status changes) can go through **pending → approved/rejected** with audit and optional email notifications, instead of applying business effects immediately in every case.

---

## Architecture (what exists)

| Layer | Role |
|--------|------|
| **MySQL tables** (`#__ordenproduccion_approval_*`) | Workflows, steps, requests, per-approver step rows, audit log, email queue. Seeded workflows per `entity_type`. |
| **`ApprovalWorkflowService`** | Creates requests, resolves approvers (user / Joomla group id / **named** group title), multi-step, approve/reject/cancel, calls hooks when a request is **fully approved** or **rejected**. |
| **`ApprovalWorkflowEntityHelper`** | Applies **business side effects** from hooks (timesheet rows, payment proof verified + balances, cotización `cotizacion_confirmada` + FEL scheduling from JSON metadata). |
| **Site UI** | **Administración** view, tab **Aprobaciones** — list of pending items for the current user; approve/reject tasks. |
| **Backend** | Dashboard entry linking to the site Aprobaciones tab (exact copy may vary by version). |

**Entity types** in seed SQL:

- `cotizacion_confirmation`
- `payment_proof`
- `timesheet`
- `orden_status` (workflow row exists; **not** wired from production flows yet)

Default seed step uses **`named_group`** = `Administracion` (Joomla user group **title** must match).

---

## Accomplished so far

### Schema & seed (≈ 3.102.0)

- DDL for all approval tables + idempotent INSERTs for workflows and first step per entity type.
- File: `com_ordenproduccion/admin/sql/updates/mysql/3.102.0.sql` (also reflected in install SQL where applicable).

### Site “Aprobaciones” tab & controller tasks (≈ 3.102.1)

- Tab on **Administración** for pending approvals; controller tasks to approve/reject workflow steps.
- Language keys for the tab and messages.

### Backend discoverability (≈ 3.102.2–3.102.3)

- Link from admin dashboard to site Aprobaciones; label/language loading fixes as needed.

### Entity integration — “Step 3” (≈ 3.103.0)

- **`onRequestFullyApproved` / `onRequestRejected`** implemented (with **actor user id**); delegate to **`ApprovalWorkflowEntityHelper`**.
- **Weekly timesheet approve:** tries `createRequest` + metadata (`cardno`, week range); fallback to direct DB approve if workflow cannot be created. **Reject** cancels open request then applies DB reject. Table names use `#__` / prefix-safe queries (replaced hardcoded `joomla_*` in those paths).
- **Daily bulk approve** on timesheets: still **direct** approve only; table names updated to `#__` where changed.
- **Payment proof — Verificado:** tries `createRequest` first; fallback to immediate `setVerificado` + balance refresh; handles already-verified and duplicate-pending.
- **Cotización — Finalizar confirmación:** with schema, saves uploads/instrucciones/facturación **without** setting `cotizacion_confirmada` until approval; stores JSON metadata for FEL; fallback path confirms immediately + FEL when no request is created.
- Stub migration: `com_ordenproduccion/admin/sql/updates/mysql/3.103.0.sql` (no DDL).

---

## Where you see it in the product

| Audience | Location |
|----------|-----------|
| Approvers | Site: **Administración** → tab **Aprobaciones** (`tab=aprobaciones`). |
| Submitters | Messages when submitting: timesheets (weekly approve), payment proof verification, cotización finalize — either “sent for approval” or legacy immediate effect. |
| Database | Pending rows in `#__ordenproduccion_approval_requests` / `_approval_request_steps`; audit in `_approval_audit_log`; optional emails in `_approval_email_queue`. |

---

## Pending / future work (when resuming)

Use this as a checklist; order is suggestive only.

1. **`orden_status` integration**  
   - Wire **`AjaxController::changeStatus`** (or equivalent) to create approval requests for restricted transitions.  
   - Implement **`onRequestFullyApproved` / `onRequestRejected`** branches for `ENTITY_ORDEN_STATUS` (metadata: `old_status`, `new_status`, order id).  
   - Define which transitions require approval (product decision).

2. **Daily timesheet bulk approve**  
   - Either leave as **fast path** without workflow, or introduce a distinct entity type / rules (e.g. per-day batch id) — needs design.

3. **Admin UI for workflows**  
   - Seed-only today: editing workflows/steps still via **database** (or phpMyAdmin). Optional: backend forms to manage workflows, approvers, email templates.

4. **Email queue**  
   - Confirm cron/plugin processes `#__ordenproduccion_approval_email_queue`; document failure handling and retries.

5. **Reject side effects**  
   - Only **timesheet** rejection from workflow rolls summary rows back to **pending** in helper; cotización/payment proof rejection does not revert DB state (by design unless you add columns/flows).

6. **Testing & operations**  
   - E2E: submit → approve/reject → DB state + FEL + balances.  
   - Document **group title** `Administracion` requirement for default approvers.

7. **Versioning**  
   - On resume, bump **VERSION** / **com_ordenproduccion.xml** and add a new `admin/sql/updates/mysql/x.x.x.sql` if DDL or data migrations are needed.

---

## Key files (resume here)

- `com_ordenproduccion/src/Service/ApprovalWorkflowService.php` — engine + hooks.  
- `com_ordenproduccion/src/Helper/ApprovalWorkflowEntityHelper.php` — business effects.  
- `com_ordenproduccion/src/Controller/TimesheetsController.php` — weekly approve/reject + workflow.  
- `com_ordenproduccion/src/Controller/PaymentproofController.php` — `markAsVerificado`.  
- `com_ordenproduccion/src/Controller/CotizacionController.php` — `finalizeConfirmacionCotizacion`.  
- `com_ordenproduccion/src/Controller/AdministracionController.php` — approval tasks (approve/reject workflow).  
- `com_ordenproduccion/admin/sql/updates/mysql/3.102.0.sql`, `3.103.0.sql`.

---

## Paused by design

No new approval features should be merged without updating this document and the version/changelog policy used in this repo.
