# Add, edit and set up companies

The software now offers ONLY financial year **2026–2027: 1 April 2026 to 31 March 2027**. Older year options are removed and other year selections are rejected by the server. Previously entered records are not deleted; prior-year vehicle costs still carry forward where relevant.

## Open Companies

1. Deploy the latest main branch to Hostinger and press Ctrl + F5.
2. Select your original company (the existing config.php database) and sign in as its admin.
3. Open **Companies** in the left menu.

Only an admin signed in to the original company may add connections or rename companies. Admins of another company cannot manage the global company list.

## Set up three demo companies

The browser demo already includes DEMO COMPANY ONE, TWO and THREE with separate sample records and only FY 2026–2027.

For persistent demo companies on Hostinger:

1. In Hostinger hPanel, create three NEW, empty MySQL databases and database users. Give each user access to its own database.
2. In the software, open **Companies → Set up three demo companies**.
3. Click **Set up** beside DEMO COMPANY ONE.
4. Enter that database's Hostinger hostname, full database name, MySQL username and password.
5. Click **Connect company**. The application tests the connection, creates the tables and adds fictional vehicles, bills and sales.
6. Repeat for DEMO COMPANY TWO and DEMO COMPANY THREE, using different databases.

No SSH installer is needed for this browser setup. Each freshly initialized database gets a copy of the admin account you are currently using: sign in with that same username and password. Future password resets and user edits remain independent per company.

Your original company stays intact alongside the three demos. Samples are only inserted into a completely empty database. The setup refuses to seed an existing database with business records.

## Add a real company

1. Create a separate empty database and MySQL user in Hostinger.
2. Open **Companies → Add company**.
3. Enter the company name and a stable company key, for example company_alpha.
4. Enter the MySQL hostname, database name, database username and password from Hostinger.
5. Click **Connect company**.

An empty database is initialized automatically without sample transactions. A fully installed existing Car Project database can also be connected without overwriting its tables or user accounts; use that company's existing account to sign in. Other non-empty databases are rejected.

Company keys must start with a lowercase letter and contain lowercase letters, digits, underscores or hyphens, 2–40 characters. Keys identify private document folders and cannot be renamed in the interface.

## Edit a company

1. Open **Companies** from the original company admin account.
2. Click **Edit name** beside the company.
3. Change the display name and click **Save name**.

Renaming does not change the company key, database, passwords, financial year or existing records. Connection changes are intentionally outside this form: edit your private configuration on Hostinger, test the connection and retain a backup. Never reuse another company's database name.

## Where company settings live

- config.php: original database connection and HTTPS setting.
- companies.php: optional earlier manually configured connections; still supported.
- storage/company-registry.json: connections and name overrides saved by the Companies page.
- storage/company-registry.lock: coordinates simultaneous settings changes.

The saved registry overrides matching values from the earlier files. It contains database credentials, is written with mode 0600, and must remain outside direct web access. The repository root .htaccess denies storage and all private application paths. The new files are ignored by Git; preserve them during redeployments and include them in private backups. Do not paste their contents into chat or commit them.

The PHP process needs write access to storage for browser-based company setup. Do not use 777 permissions. If settings cannot be saved, the app reports the error; a newly initialized database may need to be connected again using Add company without the demo option.

## Company and year separation

- Every company has its own MySQL database, accounts, masters, vehicles, bills, audit log and private document folder.
- Switching company signs you out; signing in is required for the selected company.
- Old browser tabs cannot save into a changed company/year without reloading.
- The only selectable year is 2026–2027. Entry dates outside that period are rejected.
- One company database holds its history. Changing the visible year settings does not delete prior records.
- Vehicle margins include earlier purchase costs; this is not a statutory accounting or tax system.

## Test on Hostinger before real use

Confirm all three demo company inventories differ; add a master in one and verify it does not appear in another. Test duplicate database rejection, invalid credentials, name editing, authorization from another company's admin and restoration of a database/document/configuration backup.

The code and sample preview are supplied, but the three actual MySQL databases still need to be created in your hosting account. A GitHub deployment cannot create databases without your Hostinger credentials and permissions.
