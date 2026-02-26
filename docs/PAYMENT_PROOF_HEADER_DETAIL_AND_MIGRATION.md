# Comprobante de Pago: Header + Details and Migration

## Current data model (already header + details)

The database **already** uses a header–detail structure:

| Level   | Table                      | Role |
|--------|----------------------------|------|
| Header | `#__ordenproduccion_payment_proofs` | One row per "comprobante" → one ID (e.g. PA-00052). Holds first/legacy document data and one `file_path`. |
| Details (documents) | `#__ordenproduccion_payment_proof_lines` | One row per payment document (cheque, retención, NCF, etc.) for that proof. |
| Details (orders) | `#__ordenproduccion_payment_orders` | One row per work order + amount applied for that proof. |

So:

- **One form submit** = one row in `payment_proofs` (one PA-xxxxx).
- That proof can have **many lines** (payment_proof_lines) and **many orders** (payment_orders).

## Why you see two IDs (PA-00052 and PA-00053) in the screenshot

Those two IDs exist because **two separate "Registro de comprobante" actions** were done (two form submissions):

1. First submit → Proof PA-00052: one document (e.g. Cheque 26146), one order.
2. Second submit → Proof PA-00053: one document (e.g. Retención 1742425545), same order.

So the **model** already supports one header with multiple documents and multiple orders; the situation in the screenshot is **two headers** created by two registrations.

To get **one** proof ID with **two** documents (Cheque + Retención) for the same order, the user should:

- Use **"Agregar línea"** in the form to add the second payment line.
- Submit **once** → one proof (one PA-xxxxx) with two rows in `payment_proof_lines` and the right rows in `payment_orders`.

No schema change is required for that behavior.

---

## Next steps (if you want to align UX and data)

### 1. Clarify in the UI (no migration)

- In "Registro de comprobante de pago", make it explicit that:
  - **One comprobante** = one proof ID.
  - Use **"Agregar línea"** to add more payment documents (cheque, retención, NCF, etc.) to that same comprobante.
- In "Pagos existentes", you can keep one row per proof and show its lines as sub-rows (already partially done when a proof has multiple lines), so it’s clear that one PA-xxxxx can have several documents.

### 2. Optional: Merge existing “duplicate” proofs (migration)

If you want to **convert** existing data where the same order has **several proofs** (e.g. PA-00052 and PA-00053) into **one proof** with **multiple lines**:

#### 2.1 Merge criteria

- Group proofs that share at least one **order** in `payment_orders` (e.g. all proofs that apply to order ORD-005925).
- Optionally restrict to same **created** date or same **created_by** so you don’t merge unrelated proofs.

#### 2.2 Merge logic (high level)

1. **Choose a master proof** per group (e.g. oldest `created` or smallest `id`).
2. **Move lines** from the other proofs (slaves) into the master:
   - `UPDATE payment_proof_lines SET payment_proof_id = :master_id WHERE payment_proof_id IN (:slave_ids)`.
3. **Merge payment_orders**:
   - For each slave, either:
     - Insert rows into `payment_orders` with `payment_proof_id = master_id` (and same `order_id`, `amount_applied`), or
     - If the master already has a row for that `order_id`, sum `amount_applied` and update (depending on business rules).
4. **Attachments (`file_path`)**  
   Currently there is **one** `file_path` per proof (on `payment_proofs`), not per line. So:
   - **Option A:** Keep only the master’s file; document that slave files are no longer linked (or copy them to a backup folder and note in a log).
   - **Option B (schema change):** Add a `file_path` (or `attachment_path`) column to `payment_proof_lines`, move each proof’s file to its corresponding line, then the merged proof can show one file per document. This requires a small DB change and updates in the code that read/display the file.

5. **Deactivate or delete slave proofs**  
   Set `state = 0` on the slave proofs (or delete them if you don’t need to keep them for audit). Update any code that might still reference the old proof IDs (e.g. reports, exports).

#### 2.3 Migration script (outline)

- Run in a **test** copy of the DB first.
- For each order that has more than one active proof (`state = 1`):
  - Load all those proofs (and optionally filter by date/user).
  - Pick master (e.g. min `id`).
  - Reassign `payment_proof_lines` to master.
  - Merge `payment_orders` (insert or sum by order_id).
  - Handle `file_path` (Option A or B above).
  - Set slave proofs to `state = 0` (or delete).
- Log merged pairs (master_id, slave_ids) and any file_path changes for audit.

---

## Summary

| Goal | Action |
|------|--------|
| **One proof ID, many documents, many orders** | Already supported. Use one form submit with "Agregar línea" for multiple documents. |
| **Clearer UX** | Copy and UI: stress "one comprobante = one PA-xxxxx" and show lines as details under that ID. |
| **Fix existing data** | Run a one-time migration that merges proofs per order (and optionally date/user), moves lines and orders to a single master proof, handles file_path (keep one or add file per line), and deactivates or deletes the merged proofs. |

If you want to implement the migration, the next concrete step is to decide how to handle `file_path` (Option A vs B) and then implement the merge script (and, for Option B, the schema change and code to use per-line file).
