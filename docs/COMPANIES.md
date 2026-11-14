# Companies and financial years on Hostinger

Use the same website from any browser on one Windows PC. Nothing needs to be installed on the PC. Internet is required; data stays on Hostinger.

## What changes

- Company and financial-year selectors appear on the sign-in page and in the signed-in workspace.
- Each company uses a separate MySQL database, with its own user accounts, masters, vehicles, bills, files and audit log.
- Changing company signs you out. Log in with an account installed in that company.
- Changing the financial year within the same company keeps the session, but issues a fresh workspace token.
- Old tabs cannot save or download against a newly selected company/year without reloading.
- Financial years run 1 April through 31 March. Years through the next financial year appear automatically.
- A company keeps one database across years. Earlier unsold vehicles carry into later inventory with their recorded costs. Purchases and sales lists show transactions in the selected year; vehicle margins for sales in the year include their earlier purchase and expense costs through that year's end.
- Reports are vehicle margin reports, not statutory accounts. Expenses added in later years can change lifetime margin, but are not backdated into an earlier year's snapshot.
- Earlier sold vehicles are excluded from the current workspace inventory unless they have activity in the selected year. Masters and document records are shared across years within the company.
- Company files live in separate private document subfolders. The existing company's original files stay where they are.
- Selecting another year never copies or deletes records.

## Keep the existing company

Your existing config.php and database are the company key `existing`. No schema migration is required for existing data. Add optional values to config.php:

```php
'company_name' => 'YOUR COMPANY NAME',
'first_year' => 2020,
```

The default earliest selectable year is 2000. Set first_year to your actual earliest financial-year start. Existing users may need to sign in once after deployment.

## Create three demo companies with real separate databases

The interactive browser preview already includes three isolated sample companies. The following steps create actual persistent demo databases on your hosting.

1. Back up the existing database and private document folder. Do not reinstall or seed the existing company.
2. In Hostinger hPanel, create three NEW MySQL databases and database users: one each for demo_one, demo_two and demo_three. Give each user access only to its own database. Actual names usually have your account prefix.
3. On Hostinger only, copy companies.example.php to companies.php. Replace all placeholders with the actual database names, usernames and passwords. Keep this file private and do not commit it. Each company must use a different database; duplicate connection identities are rejected.
4. Redeploy main with the new source, preserving config.php, companies.php and storage. These local files must not be removed by deployment cleanup.
5. In SSH, enter the project folder:

```bash
cd ~/domains/thecarproject.in/public_html
```

6. Install each new company with a password you choose. Example for demo_one; run again for demo_two and demo_three by changing only the --company key:

```bash
{
  IFS= read -r -s -p "Company admin password (12-72 characters): " tcp_password
  printf '\n'
  php bin/install.php --company=demo_one --name="Demo Admin" --username=superadmin --password-stdin <<< "$tcp_password"
  unset tcp_password
}
```

This does not call PHP shell_exec and does not close your SSH terminal. Passwords are hidden. Use an existing company password-reset command with the matching --company key if an account already exists; never reinstall an existing database.

7. Populate fictional records in the three NEW demo databases:

```bash
php bin/seed-demo.php --company=demo_one
php bin/seed-demo.php --company=demo_two
php bin/seed-demo.php --company=demo_three
```

The seeder refuses the existing company and refuses any demo database that already contains business records or business masters. It does not reset or erase anything. Each demo gets different vehicle numbers and prices, two vehicles, separate bills, a sale, and a prior-year vehicle carried into this year. You see the three demo companies alongside your original company.

8. Open your website and press Ctrl + F5. Choose DEMO COMPANY ONE, TWO or THREE, choose the financial year and sign in using that company's account.

## Add real companies later

Add a new stable key and connection in companies.php, create its separate database/user in Hostinger, and run the installer with --company=that_key. Do not reuse a demo database for real business without separately deciding how to remove sample data. No database creation permissions are needed by the web application.

Keys must start with a lowercase letter and contain only lowercase letters, digits, underscore or hyphen, 2-40 characters total. Never rename a key after document uploads without moving its document folder as part of a planned migration.

## Reset an account in the correct company

```bash
{
  IFS= read -r -s -p "New password (12-72 characters): " tcp_password
  printf '\n'
  php bin/reset-password.php --company=demo_one --username=superadmin --password-stdin <<< "$tcp_password"
  unset tcp_password
}
```

Omitting --company selects your original company. It does not reset an identically named user in other companies.

## Backups and security

Back up every company's database and matching document folder. Keep config.php and companies.php out of Git and public file downloads. Root .htaccess already allows only application entry points/assets; do not remove it. HTTPS/session settings come from the original config.php. All companies currently run on the same configured MySQL server model; separate users reduce the impact of a mistaken connection credential. Every company retains the existing role permissions.

## Verification before real use

- Choose each of the three demos and confirm that vehicle numbers, bills and totals differ.
- Create a master/bill in one demo, switch company and verify it does not appear in another.
- Choose the previous year and confirm later transactions disappear; the unsold vehicle should still be available when returning to the current year.
- Keep an old entry tab open, switch company/year in another tab, and confirm the old tab is rejected before saving.
- Confirm that documents from one company cannot be downloaded using a document ID from another.
- Check passwords and roles independently in each database.
- Confirm a database/file backup can be restored.

The application code and sample-data tests are supplied; Hostinger database creation, configuration and live end-to-end verification require your hosting account and are not performed by a GitHub commit.
