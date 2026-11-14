# Companies: add, edit and automatic database setup

## Daily use

Sign in to the original company as an administrator and open **Companies**.

**Add company** asks only for company name, financial year (2026–2027), address, contact person, mobile number and email. The server generates the company key, database name, database username and a random password. Hostinger creates the database and the application initializes its tables and copies the current administrator login hash. Choose the new company using **Change company / year**, then sign in with your current administrator username and password.

**Edit company** updates the name, address and contact information. It preserves the database connection, records and users. New companies start with 2026–2027. Later years are added individually from Edit company; adding a year does not move or copy transactions.

The three **Create demo** buttons use the same automatic setup and add fictional samples to a new empty database. They never seed an existing company database. The browser demo simulates separate company datasets; it does not provision real MySQL databases.

## One-time Hostinger connection (server administrator)

Deploy the latest code first. No schema migration is needed for existing companies. Do not run the installer again.

Database creation uses Hostinger's `POST /api/hosting/v1/accounts/{username}/databases` API. This requires a Hostinger API token with access to the hosting account and available database quota. An API token is different from a MySQL password. Availability and permissions must be verified on the actual hosting account.

Create `storage/company-provisioning.php` on the server (never in GitHub):

```php
<?php
return [
 'api_token' => 'YOUR_HOSTINGER_API_TOKEN',
 'account' => 'YOUR_HOSTING_ACCOUNT_USERNAME', // e.g. u123456789
 'domain' => 'thecarproject.in',
 'host' => 'localhost',
];
```

Set its permissions to `600`. The existing server rules deny web access to `storage`. PHP must have cURL and PDO MySQL enabled and be able to connect to `https://developers.hostinger.com`. Alternatively set `HOSTINGER_API_TOKEN`, `HOSTINGER_ACCOUNT` and `HOSTINGER_DOMAIN` in the server environment. The token stays server-side and is never returned by the application API or included in browser forms.

Once configured, adding companies requires no technical database fields. Existing `config.php`, `companies.php` and registry connections continue to work.

## Failure recovery

Missing hosting configuration shows an actionable setup message before any database request. A rejected API request (HTTP 400/401/403/404/422/429) may be retried after correcting the hosting connection or quota. The generated credentials are retained privately in `storage/company-setup-<key>.json`, so a confirmed rejection can safely receive a fresh short database name and password on retry.

A timeout or uncertain response blocks automatic retry: verify the generated database in hPanel before proceeding. The setup record contains credentials; do not share or commit it. If creation actually succeeded, the administrator can change its stage from `requesting` to `provisioned`, then retry the same company name. If hPanel confirms no database was created, remove only that pending setup record and retry. Never delete an existing company database to resolve a setup error.

A database connection failure after creation leaves the `provisioned` record for retry. A partially created schema or failed admin initialization requires administrator inspection; the application does not overwrite tables or delete databases. A completed setup whose registry write failed can retry using its `initialized` record. Back up the private company registry and credentials along with each company database.

Provider reference: https://github.com/hostinger/api-php-sdk/blob/main/docs/Api/HostingDatabasesApi.md

## HTTP 422 password correction

Deploy the latest code and retry the same company name. Generated database passwords now always include uppercase, lowercase, digits and a symbol. For a previously rejected request, the private setup record receives short database/user names and a compliant replacement password before retrying. Existing initialized databases and their passwords are never changed. HTTP 422 errors now show recognized validation fields and rules without exposing submitted values. An uncertain timeout remains blocked for administrator verification; do not delete setup records or existing databases merely to retry a validation error.

## Database name too long (Hosting:9999)

New company databases now use a short alphanumeric suffix; the API adds the hosting-account prefix, and PHP stores that same full name for its connection. The complete generated name is kept within a conservative 16-character budget, including the account prefix. On retry, explicitly rejected setup records are regenerated automatically with short names. Do not delete the setup file: retry the same company name after deploying the update. Records already marked provisioned, schema_ready, initialized or requesting are never renamed by this correction.


## Add the next financial year to an existing company

1. Sign in to the original company as administrator and open **Companies**.
2. Click **Edit company** on the company you want to extend.
3. Click **Add financial year 2027–2028** and confirm **Add 2027–2028**.
4. Close the editor, open **Change company / year**, select that company and **2027–2028**, then open the workspace.

The next year runs from 1 April 2027 to 31 March 2028. Repeat the same process later for 2028–2029. Only the next year can be added; duplicate and skipped years are rejected. Only explicitly added years appear, beginning with 2026–2027.

The company uses its existing database and login accounts. Masters and documents remain shared within the company. Unsold vehicles and their earlier purchase/expense costs remain available. Previous-year sales remain in their original year; transactions are not duplicated. You can switch back to older years. This is not an accounting closing process and does not post tax, cash or bank opening balances. No Hostinger API call, new database, migration or installer run is needed to add a financial year. Company details being edited remain in the editor after adding the year.


## Load 50 vehicles into THE CAR PROJECT for report testing

After deployment, sign in to the original **THE CAR PROJECT** company as administrator. Open **Companies** and click **Load 50 demo vehicles** beside that original company, then confirm **Load 50 sample vehicles**. The loader button is not offered for other companies, and the server rejects a request targeting another company. It does not create another database.

This adds 50 purchases, 35 sales (30 profits and 5 losses), 15 unsold vehicles and 100 unique expense bills in FY 2026–2027. Vehicle numbers are PB10TD5001–PB10TD5050. Descriptions, counterparties and bills are labelled REPORT DEMO. Existing entries are preserved; these sample totals are added to any existing company activity. No records are removed from any company. A repeated load is blocked, and any insertion failure rolls back the entire sample batch.

Choose THE CAR PROJECT and FY **2026–2027**, then open Overview, Vehicle inventory, Expenses and Profit reports. The sample batch alone contributes realized net profit **₹7,17,800.00**, sales revenue **₹1,66,40,150.00**, total sold vehicle costs **₹1,59,22,350.00**, unsold investment **₹63,83,400.00**, 35 completed sales and 15 unsold vehicles. Existing business records may change the displayed combined totals.

All UI currency amounts now use full Indian rupee formatting, such as ₹3,34,000.00 and ₹5,60,000.00, including dashboard cards, profit reports, chart summaries and chart tooltips. No lakh abbreviations are used.
