# Companies: add, edit and automatic database setup

## Daily use

Sign in to the original company as an administrator and open **Companies**.

**Add company** asks only for company name, financial year (2026–2027), address, contact person, mobile number and email. The server generates the company key, database name, database username and a random password. Hostinger creates the database and the application initializes its tables and copies the current administrator login hash. Choose the new company using **Change company / year**, then sign in with your current administrator username and password.

**Edit company** updates the name, address and contact information. It preserves the database connection, records and users. Financial year is currently fixed to 2026–2027; editing it does not move or copy existing transactions.

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

Missing hosting configuration shows an actionable setup message before any database request. A rejected API request (HTTP 400/401/403/404/422/429) may be retried after correcting the hosting connection or quota. The generated credentials are retained privately in `storage/company-setup-<key>.json`, so retrying does not generate another database name.

A timeout or uncertain response blocks automatic retry: verify the generated database in hPanel before proceeding. The setup record contains credentials; do not share or commit it. If creation actually succeeded, the administrator can change its stage from `requesting` to `provisioned`, then retry the same company name. If hPanel confirms no database was created, remove only that pending setup record and retry. Never delete an existing company database to resolve a setup error.

A database connection failure after creation leaves the `provisioned` record for retry. A partially created schema or failed admin initialization requires administrator inspection; the application does not overwrite tables or delete databases. A completed setup whose registry write failed can retry using its `initialized` record. Back up the private company registry and credentials along with each company database.

Provider reference: https://github.com/hostinger/api-php-sdk/blob/main/docs/Api/HostingDatabasesApi.md

## HTTP 422 password correction

Deploy the latest code and retry the same company name. Generated database passwords now always include uppercase, lowercase, digits and a symbol. For a previously rejected request, the private setup record retains its database name and generates a compliant replacement password before retrying. Existing initialized databases and their passwords are never changed. HTTP 422 errors now show recognized validation fields and rules without exposing submitted values. An uncertain timeout remains blocked for administrator verification; do not delete setup records or existing databases merely to retry a validation error.
