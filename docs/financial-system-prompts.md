# Financial System — Execution Prompts (Study Zone)

Copy one prompt per Claude Code session, in order. Don't start a phase before reviewing and approving the previous one.

---

## Phase 1 — Database & Models

```
Implement Phase 1 of the Study Zone buffet financial system on a new branch: feature/financial-system.

Context:
- Laravel 13 + Filament 5 project. Already has: Center, Service, ServiceCategory, ServicePrice (per-center pricing), StudentEntry/EntryService, LockedMonth, ActivityLog trait.
- Approved decisions: amounts stored in the NEW currency only (old currency is display-only, ×100); stock is deducted on invoice collection (payment), not on draft; there are NO free invoices — promos (e.g. 3+1) are deducted from "team allocations" as a stock movement with no revenue.

Deliverables — migrations + models + factories + seeders for the following tables, all scoped by center_id and user_id:
1. inventory_items: name, category, unit (piece/box/kg/ream/meter), purchase_cost, sale_price, min_stock_threshold, supplier, last_purchase_date, is_active.
2. stock_movements: item_id, type (purchase, sale, adjustment, team_allocation), quantity (signed), cost, optional invoice reference, note. Current stock = SUM of movements.
3. invoices: per-center sequential number, status (paid, unpaid, cancelled), beneficiary name, optional student number, discount, total, payment method (cash only for now), note, nullable shift_id.
4. invoice_items: invoice_id, line of two kinds — inventory_item_id OR service_id (polymorphic, or two nullable FKs with a check), quantity, unit price snapshot at sale time, line total.
5. expenses: type (operations, maintenance, warehouse_purchase, transport, hospitality, advance, other), payee/supplier, amount, date, note, optional receipt attachment.
6. cash_shifts: center, user, opened_at/closed_at, opening cash, computed cash income, computed expenses, actual counted cash at close, difference, closing notes, status (open, closed).

Rules:
- Follow the existing project conventions for migrations and models (guarded, casts, relations, ActivityLog trait).
- Full Eloquent relationships + an accessor for current stock.
- Monetary values as integers (new currency has no decimals) — but first check how the existing code stores amounts and stay consistent.
- Write basic Pest tests: stock computed from movements; invoice total with discount.
- Do NOT touch any existing table.
- Run php artisan migrate on the dev database only + run the tests and show me the results.
```

---

## Phase 2 — Inventory

```
Implement Phase 2 (Inventory) on branch feature/financial-system — Phase 1 (tables & models) is done.

Deliverables — a Filament InventoryItemResource under a new navigation group "النظام المالي":
1. Items table with columns: name, category, unit, current stock (computed), min threshold, purchase cost, sale price, supplier, last purchase date, status.
2. Filters: category, unit, stock status (below threshold / in stock / out of stock).
3. Red badge/row tint when stock <= threshold, gray when out of stock.
4. "Purchase entry" action: quantity + cost + supplier → creates a purchase stock_movement and updates last_purchase_date.
5. "Stock adjustment" action: actual counted quantity → creates an adjustment movement for the difference, with a required note.
6. Relation manager for the item's movements (latest movements with type and reference).
7. Excel export of the current table with applied filters.
8. Center users see only their center's items; admins see all with a center filter.

Rules:
- Arabic RTL UI, same style as existing resources (see ServiceResource as reference).
- Watch for N+1 on the computed stock column — use withSum or a subquery.
- Pest tests: purchase increases stock; adjustment records the correct difference; a center user cannot see another center's items.
```

---

## Phase 3 — Point of Sale

```
Implement Phase 3 (POS) on branch feature/financial-system — Phases 1 and 2 are done.

Deliverables — a custom Filament page (Livewire) "نقطة البيع" mimicking a POS screen:
1. Catalog side: grid of item/service cards with category tabs (All, دخوليات, طباعة, قرطاسية, مشروبات, مأكولات, حجوزات, خدمات) + search field. Items come from inventory_items; services come from the existing services with the user's center price (ServicePrice).
2. Invoice draft side: lines with editable quantity, remove line, beneficiary name and optional student number, discount (amount or percent, permission-gated), note.
3. Buttons: "تحصيل وإصدار" (creates a paid invoice + sale movements that deduct stock for inventory items only + prints), "حفظ غير محصلة" (unpaid invoice, NO stock deduction), "تفريغ".
4. "Unpaid invoices" screen/modal: list + collect button (turns it paid and deducts stock at that moment) + cancel (permission-gated).
5. "Team allocations" (مخصصات الفريق): a separate action to pick items and quantities → team_allocation movements that deduct stock with no invoice and no revenue, with recipient name and note.
6. Block collection when an item's stock is insufficient (clear Arabic message).
7. Simple thermal invoice print view: logo, center, number, lines, discount, total, date and user.

Rules:
- Stock deduction happens ONLY on collection — inside a DB transaction with lockForUpdate on the item stock.
- Line price is a snapshot at sale time; later price changes must not affect it.
- Block sales in a locked month (LockedMonth).
- Pest tests: collection deducts stock; unpaid does not; later collection deducts; insufficient stock is rejected; discount math is correct.
```

---

## Phase 4 — Expenses

```
Implement Phase 4 (Expenses) on branch feature/financial-system.

Deliverables — an ExpenseResource under the "النظام المالي" group:
1. Expense form: type (operations, maintenance, warehouse_purchase, transport, hospitality, advance, other), payee/supplier, amount, date, center (auto for center users), note, optional receipt image.
2. Table with filters: type, date range, center (admins only).
3. Total of the currently filtered expenses shown above the table.
4. When type = warehouse_purchase: show an optional repeater (item + quantity + cost) that automatically creates purchase stock movements linked to the expense.
5. Block creating/editing an expense in a locked month.
6. Edit/delete gated to managers only, with ActivityLog.

Pest tests: warehouse purchase creates the movements; locked month is rejected; filtered total is correct.
```

---

## Phase 5 — Cash Shifts

```
Implement Phase 5 (Cash & Shifts) on branch feature/financial-system.

Deliverables — a Filament page "الصندوق والورديات":
1. Open shift: opening cash + record center, user, time. Max ONE open shift per center.
2. POS integration: every paid invoice and every expense records the open shift's shift_id. Block collection with no open shift (message directing to open one).
3. Close shift: reconciliation screen showing computed cash income, recorded expenses, expected net; user enters actual counted cash → compute difference; require a note when difference != 0.
4. Printable shift-close report: period, invoice count, income, expenses, difference, user.
5. Past shifts log with period and center filters, read-only after closing.

Rules:
- A closed shift is immutable — corrections happen as later movements, never edits.
- Pest tests: no collection without a shift; computed income is correct; difference math is correct; no two open shifts per center.
```

---

## Phase 6 — Reports & Dashboard

```
Implement Phase 6 (Reports) on branch feature/financial-system.

Deliverables:
1. Dashboard widgets (for finance users): today's sales, today's expenses, today's net, invoice count, items below threshold.
2. Revenue chart: daily line for a selectable period (last 7/30 days or custom); admins can compare centers.
3. Sales distribution: pie by category (دخوليات, طباعة, قرطاسية, مشروبات, مأكولات, حجوزات, خدمات) for the selected period.
4. "التقرير المالي" page: unified table (income + expenses) with filters: date range, center, movement type (all/income/expenses), category — totals and net above the table, and a clean print button.
5. Old-currency display toggle (×100 for display only — storage is always new currency).
6. "All centers" filter visible to admins only.

Rules:
- Aggregate queries, never loading all rows — mind performance as data grows.
- Pest tests: unified report totals are correct under every filter.
```

---

## Phase 7 — Permissions, Testing & Release

```
Implement Phase 7 (Permissions & release prep) on branch feature/financial-system.

Deliverables:
1. Permissions using the project's existing mechanism: cashier (POS + read-only inventory + own center's expenses), center manager (all screens and reports for their center, gated edits), admin (everything + all centers + prices + month locking).
2. Wire month locking into ALL financial writes (invoices, expenses, stock movements, shifts) — audit thoroughly for gaps.
3. Verify ActivityLog covers: invoice create/edit/cancel, expense, stock movement, shift open/close.
4. Security review: mass assignment, authorization on every action, center_id checks on every query.
5. Run the FULL project test suite and confirm nothing existing broke.
6. Realistic demo seeder for one center to present to management.
7. A manual QA checklist I can walk through myself before release.
```

---

## Phase 8 — Legacy Data Import

```
I have SQL Server database files (mdf + ldf) from an old desktop financial app called DBRestaurantProV5, one file per center. Help me step by step:

1. Install SQL Server Express on my machine (Windows 11) and attach the first file.
2. Explore the tables and columns, and build a mapping between the legacy schema and our new tables (inventory_items, stock_movements, invoices, invoice_items, expenses).
3. Write an artisan command: php artisan finance:import-legacy {center} {connection} — imports items, then stock as an opening-balance movement, then historical invoices and expenses, converting currency if legacy amounts predate the zero-removal.
4. Make the import idempotent: re-running it must not duplicate data (use a legacy_id reference key).
5. After each import, produce a reconciliation report: record counts and totals, legacy vs new.

Work on COPIES of the files, never the originals, and never write to the production database.
```

---

## General notes for every session

- Each phase = one or more commits on `feature/financial-system` with clear messages.
- If Claude finds a conflict with the existing project structure: stop and explain before implementing.
- After each phase: test it yourself in the browser before moving on.
