# Yard expenses, sale/expense editing and inventory exports

## Deployment and existing yard costs

Deploy the latest code and refresh the browser. Do not rerun the installer or delete purchase records. On the next authenticated request, each company automatically creates the `yard_expenses` table if needed and copies its historical embedded purchase-yard amounts once. The database account needs CREATE TABLE permission for this first upgrade. Company databases remain separate.

Old source columns are retained for compatibility, but cost calculations use the independent yard records, so costs are not counted twice. Historical release dates were not stored: migrated records show **Not recorded · legacy purchase** until a user records the actual release date. Their original purchase date is used only to retain their financial-year allocation. A unique legacy purchase link prevents repeat migration, including after soft deletion.

## Yard expenses

Open **Yard expenses → Add yard expense**. Choose a purchased vehicle and yard, then enter release date and amount. The yard master supports instant creation using **+**. Release date must be on or after purchase and within the selected financial year.

Use **Edit** to correct an entry or **Delete** to remove its cost from reports. Deletion is soft: the database retains the row and audit history. Admins, managers and accountants can manage yard entries; viewers can read them. Multiple independent yard costs can be recorded for a vehicle. These costs update stock investment, sale profit, ledgers and inventory exports.

Purchase entry no longer contains yard name or yard expenses. Editing a purchase preserves existing independent yard records. A purchase date cannot be moved after a recorded yard release date. Legacy yard allocations without a recorded release date follow a corrected purchase date.

## Edit sale

Open **Sales → Edit sale**. Admins and managers can correct buyer, sale date, sale amount, sale expenses and salesperson. The vehicle identity, original creator and linked purchase are retained. Edit within the original sale financial year; the sale date cannot precede purchase. Profit recalculates after saving.

## Edit expense

Open **Expenses → Edit expense**. Admins, managers and accountants can correct party, bill number, date, item names and line totals. Bill numbers remain unique. Bill dates must be strictly later than the vehicle purchase date and within the selected year. Historical bills with multiple items retain every item and its stored quantity; line totals remain full amounts, not quantities multiplied again. Linked vehicle, item IDs and audit records remain intact.

## Vehicle inventory exports

Open **Vehicle inventory → Export Excel / PDF / CSV**. Every format uses the current company, financial year, stock/sold tab and search filter.

- **Excel (.xlsx):** a genuine Excel workbook, with numeric amount cells, Unicode text and full amounts. No third-party library or external service is required.
- **CSV (.csv):** UTF-8, quoted values, full numeric amounts and protection against formula injection in text fields.
- **PDF / Print:** opens an A4 landscape report. Choose **Save as PDF** in the browser print dialog. Allow the report pop-up when prompted.

The report includes registration/model, purchase date and amount, yard costs, expense bills, total cost, sale date/amount/costs, net profit and status. Exports do not modify business records.

## Validation

Automated checks exercise repeatable legacy migration, no double counting, yard create/update/soft-delete, sale and expense corrections, chronology, rollback, purchase compatibility, role-based UI controls, filtered exports and actual XLSX parsing with Unicode and numeric cells. Browser checks used fictional preview data. The live Hostinger database upgrade still requires deployment and acceptance checking.
