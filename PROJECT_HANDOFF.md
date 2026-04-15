# com_ordenproduccion — progress handoff (local)

**Last updated:** 2026-04-15  
**Component version:** `3.109.48-STABLE` (see `VERSION` and `com_ordenproduccion/com_ordenproduccion.xml`)

This file is a **local checkpoint** so future work can resume where we left off. It is not a substitute for `CHANGELOG.md`.

---

## Telegram webhook (inbound)

- **Endpoint:** `index.php?option=com_ordenproduccion&controller=telegram&task=webhook&format=raw` (POST; secret header `X-Telegram-Bot-Api-Secret-Token`).
- **Logging:** Table `#__ordenproduccion_telegram_webhook_log` (migration `admin/sql/updates/mysql/3.109.41.sql`). Helper: `src/Helper/TelegramWebhookLogHelper.php`.
- **Controller:** `src/Controller/TelegramController.php` — logs every exit path with `outcome` codes; saves mismatch comments via `PaymentsModel::addMismatchTicketCommentAsUser(..., false, 'telegram')` so Telegram-originated comments **do not** re-queue `notifyMismatchTicketCommentAdded` (no echo DMs).
- **Anchor + fallback:** `TelegramMismatchAnchorHelper` — registry lookup; if no row, parses `PA-########` from replied-to message text; can backfill anchor after success.

---

## Mismatch ticket UI (payments + payment proof)

- **Comments `source`:** Column `source` (`site` | `telegram`) on `#__ordenproduccion_payment_mismatch_ticket_comments` — `3.109.46.sql`. Web = site; webhook = telegram.
- **Bubbles:** Web = right blue; Telegram = left white + blue accent. Meta line includes **Web** / **Telegram** labels.
- **Dates:** `SiteDateHelper` + `HTMLHelper::date()` — Joomla **Global Configuration → Server Time Zone** (e.g. America/Guatemala). JSON exposes `created_display` for comments; delete-preview JSON has `proof.created_display`.
- **Live modal:** While mismatch modal is open, **poll `getMismatchTicket` every 4s** (`MISMATCH_POLL_MS`), quiet refresh, preserves draft, smart scroll (pin to bottom only if user was near bottom). Stops on `hidden.bs.modal`; pauses when tab hidden.
- **Templates:** `tmpl/payments/default.php`, `tmpl/paymentproof/default.php` (shared mismatch modal JS/CSS patterns).

---

## Grimpsabot admin UI

- Tab **Webhook log** — reads `#__ordenproduccion_telegram_webhook_log`, pagination `tg_wlp`. Queue URLs preserve `tg_wlp`.
- **Grimpsabot** `tmpl/grimpsabot/default.php` — date columns use `SiteDateHelper` for queue + webhook log tables.

---

## API / controller touchpoints

- `PaymentsController::getMismatchTicket` — comments include `source`, `created_display`; `current_user_id` (legacy alignment removed in favor of source-based bubbles).
- `PaymentsController::getPaymentDetails` — `proof.created_display`.

---

## After deploy

1. Run Joomla **Extensions → Database** so pending SQL updates (e.g. `3.109.41`, `3.109.46`) apply.
2. Confirm **Server Time Zone** in Global Configuration.
3. **Webhook:** Telegram must POST with the configured secret; cron must run for outbound queue if you use queued anchor DMs.

---

## Possible follow-ups (not done)

- Push-based updates (SSE/WebSocket) instead of 4s polling — would need server support.
- Backfill historical `source=telegram` for rows before `3.109.46` — not possible without external data.
- Tune `MISMATCH_POLL_MS` or make it a component param.

---

## Git

Latest work should be on `main` with conventional commits; verify with `git log -5`.
