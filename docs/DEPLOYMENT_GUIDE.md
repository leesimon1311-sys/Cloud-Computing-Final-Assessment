# Deployment Guide

Everything needed to reproduce both deployments and collect the evidence the assessment
asks for. Part 1 is already done on this machine; it is written down so you can repeat it
and so the steps can be described in the report.

The local database is managed entirely through **phpMyAdmin**. The command line scripts in
`tools\` exist for the steps phpMyAdmin cannot do (writing the `.env` file, generating a
bcrypt hash, configuring the firewall), and as a faster alternative if you prefer.

---

## Part 1 — Local infrastructure (Task B)

### 1.1 Start the XAMPP services

Open the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`) and press **Start** for
**Apache** and **MySQL**. Both modules should turn green and show a PID and ports
(Apache 80, 443; MySQL 3306).

> **Appendix A, Figure A1** — screenshot this panel.

### 1.2 Make the project reachable by Apache

The project folder is linked into Apache's document root with a directory junction, so
there is only one copy of the code and edits appear immediately:

```powershell
cmd /c mklink /J C:\xampp\htdocs\inventory "C:\Users\Asus\Documents\Cloud Computing FA"
```

Copying the folder to `C:\xampp\htdocs\inventory` works equally well.

### 1.3 Secure the database before anything else

**This step is worth marks.** Section 5 and Task F both require that you *"avoid using an
unsecured default database configuration"*. A fresh XAMPP install is exactly that:

| Default | Risk |
|---|---|
| MySQL `root` has no password, on all three host entries | Anyone reaching the machine has total control of every database |
| phpMyAdmin uses `auth_type = 'config'` | It signs in as root automatically and never asks who you are |
| `AllowNoPassword = true` | Password-less sign in is permitted |
| `blowfish_secret = 'xampp'` | The cookie encryption key is publicly known |
| `pma` control account has no password | A second unprotected account |
| Wildcard grants on the `test` database | Any account can read and write it |

Close all of them in one step:

```powershell
powershell -ExecutionPolicy Bypass -File tools\secure_mysql.ps1
```

It sets a strong password on every `root` entry and on `pma`, removes the `test` grants and
database, and rewrites `C:\xampp\phpMyAdmin\config.inc.php` to use **cookie
authentication** with a random 32-character secret. The original config is backed up
alongside it. The generated passwords are written to `CREDENTIALS-LOCAL.txt`, which is
git-ignored — **keep it out of every screenshot**.

From now on phpMyAdmin presents a login screen at <http://localhost/phpmyadmin/>.

> **Appendix F, Figure F1** — the phpMyAdmin login screen. This single screenshot is the
> clearest possible evidence that you replaced the unsecured default, because the stock
> XAMPP configuration cannot produce it.

### 1.4 Create the database in phpMyAdmin

Sign in to phpMyAdmin as `root` with the password from `CREDENTIALS-LOCAL.txt`, then:

1. Click the **Import** tab at the **server** level (before opening any database)
2. **Choose File** → `sql\inventory_db_import.sql`
3. Scroll down and press **Import**

That one file creates the `inventory_db` database, all four tables with their indexes and
foreign key, five categories and twelve demonstration products. It finishes by printing a
row showing the counts, so you can see immediately that it worked.

The demonstration data is arranged so the dashboard shows every alert state at once: one
product out of stock, two below their reorder level, two expiring within 30 days and one
already expired.

> **Appendix A, Figure A6** — phpMyAdmin showing `inventory_db` with its four tables and
> their row counts.
> **Appendix F, Figure F6** — the `products` table **Structure** tab, showing column types,
> indexes and the foreign key.

### 1.5 Create the application's database account

The application must **not** connect as root. Create a least-privilege account that can
only touch `inventory_db`, and only to read and change rows.

**Through phpMyAdmin** (good evidence for "authentication settings"):

1. **User accounts** tab → **Add user account**
2. User name `inventory_app`, Host name **Local** (`localhost`)
3. Press **Generate** for the password, and **copy it somewhere safe**
4. Under *Global privileges* tick **nothing**
5. Create the account, then open **User accounts → Edit privileges → Database**
6. Select `inventory_db` and grant **only** `SELECT`, `INSERT`, `UPDATE`, `DELETE`

Then record the credentials by copying `.env.example` to `.env` and filling in
`DB_USER=inventory_app` and `DB_PASS=<the generated password>`.

**Or run the script**, which does all of the above including writing `.env`:

```powershell
powershell -ExecutionPolicy Bypass -File tools\setup_local.ps1 -RootPassword "<root password>"
```

Either way, verify the result — this output is the evidence:

```sql
SHOW GRANTS FOR 'inventory_app'@'localhost';
```

Run it in phpMyAdmin's **SQL** tab. It must show `USAGE` globally plus
`SELECT, INSERT, UPDATE, DELETE` on `inventory_db` only. No `DROP`, no `GRANT OPTION`,
nothing on `*.*`.

> **Appendix F, Figure F2** — this `SHOW GRANTS` output.

### 1.6 Create your login account

A user row needs a bcrypt hash, which cannot be typed by hand. Generate the statement with
a password you choose:

```powershell
C:\xampp\php\php.exe tools\make_admin_sql.php admin "YourStrongPassword" "System Administrator" admin
```

Copy the `INSERT` it prints into phpMyAdmin → `inventory_db` → **SQL** tab → **Go**. Only
the hash is in the statement; the password itself never appears.

> **Appendix F, Figure F3** — the `users` table showing the `password_hash` column with its
> `$2y$10$...` bcrypt values. This proves passwords are not stored in plain text, and it is
> safe to screenshot because a hash is not a password. Do **not** screenshot the password
> you chose.

*(Alternative: `install.php?token=...` does steps 1.4–1.6 in one request and generates a
password for you. The phpMyAdmin route above is better for the report because each step is
visible and screenshottable.)*

### 1.7 Verify through localhost

Open <http://localhost/inventory/> and sign in.

> **Appendix A, Figures A2–A5** — login page, dashboard, product list with filters, and the
> add/edit form showing a validation message.

### 1.8 Find your local IP address

```powershell
ipconfig
```

The Wi-Fi adapter's IPv4 address was **10.235.219.205** when this was set up. It changes
when you reconnect, so check again on the day.

> **Appendix B, Figure B1** — the `ipconfig` output.

### 1.9 Allow other devices through the firewall

Apache already listens on every interface, but Windows Firewall blocks inbound port 80.
Open **PowerShell as Administrator** (Start → type "PowerShell" → right-click → *Run as
administrator*) and run:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Users\Asus\Documents\Cloud Computing FA\tools\enable_lan_access.ps1"
```

It adds an inbound rule scoped to the **Private** profile only — never Public — then prints
the exact URLs to use and checks that your Wi-Fi is classified as Private. If it reports
Public, change it in *Settings → Network & Internet → Wi-Fi → your network → Private*,
or the rule will not apply.

Remove the rule when the assessment is over:

```powershell
powershell -ExecutionPolicy Bypass -File tools\enable_lan_access.ps1 -Remove
```

### 1.10 Test from another device

Connect a phone or second laptop to the **same Wi-Fi** and browse to
`http://10.235.219.205/inventory/`.

> **Appendix B, Figure B2** — the phone showing the application with the IP address visible
> in its address bar. This is the proof that the local deployment is reachable across the
> LAN but not from the internet.

---

## Part 2 — Cloud infrastructure (Task C)

### 2.1 Push the repository to GitHub

A local Git repository already exists. Create an **empty** repository on GitHub (no README,
no .gitignore), then:

```powershell
git remote add origin https://github.com/<your-username>/inventory-system.git
git push -u origin main
```

Confirm that neither `.env` nor `CREDENTIALS-LOCAL.txt` was pushed. Both are in
`.gitignore`; the repository carries `.env.example` with placeholders only.

> **Appendix C, Figure C1** — the GitHub repository page.

### 2.2 Create the Render services

1. Sign in at <https://render.com> with your GitHub account.
2. **New → Blueprint**, select the repository; Render reads `render.yaml`.
3. It proposes a web service (`inventory-system`) and a PostgreSQL database
   (`inventory-db`). Edit `region` in `render.yaml` first if Singapore is not nearest you.
4. **Apply**. Render builds the Docker image and starts both services.

The blueprint injects the database connection string as `DATABASE_URL` and generates a
random `INSTALL_TOKEN`, so no credential is typed by hand or stored in the repository.

> **Appendix C, Figures C2–C3** — the build log, and the service showing **Live**.

**On the choice of PostgreSQL in the cloud:** Render's free tier offers managed PostgreSQL,
not MySQL. The application's data layer uses PDO with SQL that both engines accept and
picks the driver from configuration, so the identical code runs on MariaDB locally and
PostgreSQL in the cloud. This is a genuinely good point to make in section 2 of the report —
portability is a real cloud design concern, not a workaround. If you would rather run MySQL
in both environments, `DATABASE_URL` also accepts a `mysql://` URL from any managed MySQL
provider, and nothing in the code changes.

### 2.3 Initialise the cloud database

Copy `INSTALL_TOKEN` from the service's **Environment** tab and open once:

```
https://<your-service>.onrender.com/install.php?token=<INSTALL_TOKEN>
```

The same installer runs, this time creating the PostgreSQL schema and printing a separate
administrator password for the cloud deployment. Save it; change it after signing in.

### 2.4 Verify the public URL

Open `https://<your-service>.onrender.com/` and sign in. Check `/health.php` too — it should
report `"environment": "production"` and `"database": "ok"`.

> **Appendix C, Figures C4–C5** — the application at the public HTTPS URL with the padlock
> visible, and the health endpoint. Record this URL in section 2 of the report; it is
> explicitly marked.

---

## Part 3 — Collecting the evidence

### 3.1 Local system resources (Appendix D)

```powershell
Get-CimInstance Win32_Processor |
  Select-Object Name, NumberOfCores, NumberOfLogicalProcessors, MaxClockSpeed
[math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory/1GB, 2)
```

This machine: **Intel Celeron N4000 @ 1.10 GHz, 2 cores / 2 threads, 7.83 GB RAM**,
Windows 11 Home Single Language. Also screenshot **Task Manager → Performance** while using
the application.

### 3.2 Cloud resources (Appendix D)

Screenshot the Render service's **Settings → Instance Type** page (free plan: 512 MB RAM,
0.1 CPU) and the database's plan page showing its 1 GB storage.

### 3.3 Response times (Appendix E)

Browser method, required by the assessment:

1. DevTools (**F12**) → **Network**, tick **Disable cache**
2. Load `http://localhost/inventory/index.php?page=dashboard`; screenshot the waterfall and
   the Load / DOMContentLoaded figures
3. Repeat on the public cloud URL
4. Take a second cloud measurement **after 15+ minutes idle** to capture a cold start —
   the most interesting number in the whole comparison

Script method, for a larger sample:

```powershell
powershell -ExecutionPolicy Bypass -File tools\measure_performance.ps1 `
  -LocalUrl "http://localhost/inventory/health.php" `
  -CloudUrl "https://<your-service>.onrender.com/health.php" `
  -Runs 15 -CsvPath docs\performance.csv
```

Baseline already measured locally (12 runs against `health.php`, 157-byte response):

| Metric | Local |
|---|---|
| First request | 105.4 ms |
| Minimum | 33.5 ms |
| Mean | 76.2 ms |
| Median | 71.9 ms |
| 95th percentile | 111.9 ms |
| Maximum | 111.9 ms |

### 3.4 Cloud monitoring (Appendix E)

The container writes Apache's access and error logs to stdout and stderr, so they appear in
Render's live log viewer. Make a few requests first, then screenshot the **Logs**,
**Metrics** and **Events** tabs.

### 3.5 Security and database evidence (Appendix F)

Most of this comes from phpMyAdmin:

| Evidence | Where |
|---|---|
| Cookie-authentication login screen | <http://localhost/phpmyadmin/> signed out |
| Least-privilege grants | SQL tab → `SHOW GRANTS FOR 'inventory_app'@'localhost';` |
| Password hashes, not passwords | `users` table → Browse |
| Schema, indexes, foreign key | `products` table → Structure |
| User accounts and their hosts | **User accounts** tab |
| **SQL export** | See 3.6 below |

Plus, from outside phpMyAdmin:

- A browser request to `http://localhost/inventory/src/db.php` returning **403 Forbidden**
- DevTools → Network → Headers showing `Content-Security-Policy` and `X-Frame-Options`
- The application's own **Activity log** page showing sign-ins and stock changes
- Output of `tools\backup_local.ps1`

**Never capture:** `CREDENTIALS-LOCAL.txt`, `.env`, the root or `inventory_app` passwords,
installer output containing a password, Render connection strings, or `INSTALL_TOKEN`.
A bcrypt hash *is* safe to show; a password is not.

### 3.6 SQL export (Appendix F, explicitly requested)

In phpMyAdmin: select `inventory_db` → **Export** tab → *Quick* → format **SQL** → **Go**.
Screenshot the Export page before pressing Go, and keep the downloaded `.sql` file — the
question paper asks for "SQL export where applicable".

For a larger sample or scripted backups:

```powershell
powershell -ExecutionPolicy Bypass -File tools\backup_local.ps1 -RootPassword "<root password>"
```

Both produce a dump containing password hashes, so keep the file private and do not paste
its contents into the report.

### 3.7 Cost evidence (Appendix G)

Screenshot <https://render.com/pricing> and <https://render.com/docs/free> on the day you
write the report — prices change, so do not rely on figures quoted in these notes.

---

## Troubleshooting

**phpMyAdmin says "Access denied".** Use the password in `CREDENTIALS-LOCAL.txt`. If that
file is gone, reset root by stopping MySQL and starting it once with
`mysqld.exe --skip-grant-tables`, then `ALTER USER 'root'@'localhost' IDENTIFIED BY 'new';`.

**phpMyAdmin is slow the first time.** Normal on this hardware — PHP compiles a large
codebase on the first request. It took 2 seconds on the second attempt here.

**Scripts fail with "Could not connect to MySQL".** `tools\setup_local.ps1` and
`tools\backup_local.ps1` now need `-RootPassword "<root password>"`, because root is no
longer password-less.

**Apache will not start — port 80 in use.** Usually IIS, Skype or another server. Find it
with `Get-NetTCPConnection -State Listen -LocalPort 80`, or change Apache's port to 8080 in
`C:\xampp\apache\conf\httpd.conf` and use `http://localhost:8080/inventory/`.

**"Database not initialised".** MySQL is not running, or `.env` has the wrong credentials.
Check `http://localhost/inventory/health.php` for the specific fault.

**GRANT appears to succeed but has no effect.** The MariaDB privilege table is corrupt —
this happened on this machine. Diagnose with `CHECK TABLE mysql.db;` in phpMyAdmin, then
stop MySQL and repair offline:

```powershell
C:\xampp\mysql\bin\aria_chk.exe --recover --force C:\xampp\mysql\data\mysql\db
```

**Lost the application password.**

```powershell
C:\xampp\php\php.exe tools\set_password.php admin <new-password>
```

**Cloud build fails on `entrypoint.sh`.** It must keep LF line endings; `.gitattributes`
enforces this. Check with `git check-attr -a docker/entrypoint.sh`.

**The cloud site is very slow on the first visit.** Expected: the free instance spins down
after 15 minutes idle and takes roughly 30–60 seconds to restart. Document it rather than
working around it — it is the clearest limitation of the free tier.
