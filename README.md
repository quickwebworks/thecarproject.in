# The Car Project — PHP / MySQL admin, v1

A dealership administration application for purchases, vehicle expenses, sales, per-car profit, reusable masters, document files, and user types. The responsive interface uses a navy and amber theme, bottom navigation on mobile, and bottom-sheet dialogs on small screens.

## What is included

- Dashboard with figures derived from entered records and monthly purchase/sale chart.
- Vehicle inventory and a linked financial ledger per vehicle.
- Purchase entry: vehicle, bank, seller, date, amount, manager/contact, yard and portal; initial yard expense.
- Sale entry: in-stock vehicle, buyer, salesperson, date, amount, sale expense, net sales and profit preview.
- Expense bills with globally unique bill number, party, date and multiple quantity/line-total items.
- Searchable master selectors and nested **Save & select** dialogs. The existing transaction remains in place without a page refresh.
- Vehicle, bank, client/seller, party, document, yard and portal masters.
- Private PDF/JPEG/PNG document storage, authenticated downloads, expiry dates and checklist.
- Admin, manager, accountant and viewer roles; create users and enable/disable other accounts.
- CSV exports and an append-only activity log for successful mutations.

## Technology

PHP 8.4 recommended; source uses PHP 8.1-compatible language features. Do not start a production installation on unsupported PHP 8.1. MySQL 8.0+ with InnoDB, PHP PDO MySQL, mbstring, fileinfo and sessions. This package is a standalone PHP application with parameterized PDO queries; it does not require Laravel, Composer, Node, a JavaScript framework, or third-party runtime services. Node is only needed to run the optional JavaScript test.

## Installation on PHP / MySQL hosting

1. Create an empty MySQL database and a database user restricted to that database. Keep credentials private.
2. Upload the application folder **outside the public web directory**, for example `/home/ACCOUNT/the-car-project`.
3. Copy `config.example.php` to `config.php`. Enter the database DSN, user and password. Keep `https_only` set to `true` on hosting.
4. In an interactive SSH terminal, run:
   ```sh
   cd /home/ACCOUNT/the-car-project
   php bin/install.php
   ```
   Enter the admin name, username, and a password of at least 12 characters. Password entry is hidden. No default password is shipped. The installer creates the schema and initial document types/portal. It refuses to overwrite an existing users table.
5. Point the domain/subdomain document root at `/home/ACCOUNT/the-car-project/public`. Only `public/` should be web-accessible. Do not expose the project root. If the hosting plan cannot change document roots, place the application outside `public_html`, move public files into `public_html`, and adjust the bootstrap/storage paths to the private project location; have your hosting administrator do this carefully.
6. Give the PHP process write access to `storage/documents` and its session directory. Restrict access to `config.php`, backups and storage to the account/PHP process. Do not use world-writable permissions.
7. Enable HTTPS for the domain. Set `upload_max_filesize=10M`, `post_max_size=12M`, and an appropriate `max_file_uploads`. Set the server timezone explicitly for the business. PHP error logs must be private, with `display_errors=Off`.
8. Open the domain and log in with the admin account created in step 4. Start with **Masters** or add masters directly in a purchase form.
9. Schedule encrypted off-server database and document backups and test restoration. Update PHP/MySQL and review access regularly. Backups and hosting operations are not automatically provisioned by this source package.

For local development only: set `https_only=false` in a local config and use `php -S 127.0.0.1:8000 -t public`. Restore `true` for HTTPS hosting.

## Roles

| User type | Access |
|---|---|
| Admin | Read all records, add all entries and masters, manage users |
| Manager | Read records, add purchase/sale/expense/master/document records |
| Accountant | Read records, add expense/master/document records |
| Viewer | Read records, reports and authenticated document downloads |

Every authenticated role can see vehicle financial figures and client/party records. This is a single-business workspace, not multi-tenant software. Server-side authorization is applied independently of button visibility. Passwords are hashed with PHP `password_hash`; sessions use HttpOnly and SameSite cookies, 30-minute inactivity expiry and ID rotation at login. Login attempts are throttled by username and source IP. All POST requests require a session CSRF token.

## Financial definitions

- Initial cost = purchase amount + yard expense.
- Vehicle cost = purchase amount + yard expense + sum of expense bill line totals.
- Net sales = sale amount − sale expenses.
- Net vehicle profit = net sales − vehicle cost.
- An expense item's `total` is the **full amount for the whole quantity**. Four tyres with a line total of ₹20,000 add ₹20,000, not ₹80,000.
- Enter any cost in only one category. Do not repeat initial yard charges as a separate expense bill.
- Unsold vehicles hold inventory cost and have no realized profit. Later bills for a sold vehicle update its displayed lifetime profit.
- Shared overhead, GST/taxes, interest and other charges affect profit only when explicitly included in recorded costs. This is vehicle margin reporting, not a statutory accounting/tax system or cash/bank reconciliation.
- MySQL stores money as DECIMAL; totals are derived for display rather than maintaining a duplicate profit balance.

## Current v1 boundaries

One purchase and one sale per vehicle master. Reacquisition of a previously sold registration needs a future acquisition-cycle model. Financial entries are create-only in this release; there is no correction/reversal, deletion, opening-balance or payment/receivable module. Masters can be added, but existing masters are not edited/deleted in the interface. Document types are configurable; missing optional documents do not block a sale. The dashboard document warning specifically checks RC and Insurance. Bank-specific metadata can be entered in the bank details field; account/IFSC fields are not separately structured.

The state endpoint loads business records into the admin session interface at once. Before a large inventory rollout, add server-side pagination and aggregates. There is no promise of performance at a particular data volume. No 2FA, automated backup agent, malware scanning service, WhatsApp integration or external portal integration is installed. Cars24 is a portal master, not a live API connection.

## Preview versus deployed PHP application

The separately hosted interactive design preview has a clearly marked sample-data mode. It stores records in browser localStorage, starts with fictional records, and bypasses real authentication. Document previews save metadata only; original file bytes are not retained. Do not enter sensitive or real business data into it.

The files in `public/` do not enable demo mode: they use `api.php` with PHP sessions and MySQL. Production starts empty except for document types and Cars24. No database or hosting credentials are included. Hosting setup and an end-to-end MySQL acceptance test are still required before using it for real business.

## Verification

Run `node tests/ledger.test.cjs` for profit, expense single-counting, duplicate sale/bill, date and role-visibility scenarios. `tests/validation.php` checks server validation helpers without modifying the database; it requires a configured `config.php`. Lint PHP source using `php -l` and run PHP/MySQL acceptance tests on the target hosting.

Browser QA completed on the sample preview: quick-add vehicle preserves purchase amount and selects the new vehicle; purchase saves; expense is linked; sale preview and saved report agree on ₹75,000 profit for a ₹5,00,000 purchase + ₹20,000 bill, ₹6,00,000 sale and ₹5,000 sale expense. JavaScript tests and PHP syntax/helper checks were run locally. MySQL transaction, schema import, login and private upload/download were not exercised against a live MySQL server in this environment.

### Target-host acceptance checklist

- Create a test admin, manager, accountant and viewer; verify direct API attempts are denied for disallowed writes.
- Verify bad credentials, throttling, CSRF rejection, logout, inactivity expiry and disabled account behavior.
- Import/install on an empty MySQL 8 database and verify duplicate vehicle purchase/sale and bill constraints.
- Create purchase → multi-item expense → sale; compare ledger, report and CSV totals.
- Verify duplicate master names/vehicle numbers and invalid dates/negative amounts are rejected.
- Upload valid PDF/JPEG/PNG; reject invalid MIME and files over 10 MB; verify document directory cannot be accessed publicly and download requires login.
- Confirm backup restoration, HTTPS cookie settings, web-root isolation and phone layouts.
