# Deployment Guide

Everything needed to reproduce both deployments and collect the evidence the assessment
asks for. Part 1 is already done on this machine; it is written down so you can repeat it
and so the steps can be described in the report.

---

## Part 1 — Local infrastructure (Task B)

### 1.1 Start the XAMPP services

Open the **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`) and press **Start** for
**Apache** and **MySQL**. Both modules should turn green and show a PID and ports
(Apache 80, 443; MySQL 3306).

> Take the screenshot for **Appendix A, Figure A1** here.

### 1.2 Make the project reachable by Apache

The project folder is linked into Apache's document root with a directory junction, so
there is only one copy of the code and edits appear immediately:

```powershell
cmd /c mklink /J C:\xampp\htdocs\inventory "C:\Users\Asus\Documents\Cloud Computing FA"
```

A plain copy of the folder into `C:\xampp\htdocs\inventory` works equally well.

### 1.3 Create the database and a least-privilege account

```powershell
powershell -ExecutionPolicy Bypass -File tools\setup_local.ps1
```

This creates the `inventory_db` database, creates the `inventory_app` account with a
randomly generated password, grants it only `SELECT, INSERT, UPDATE, DELETE` on that one
database, imports `sql/schema_mysql.sql`, and writes a `.env` file.

This step is what replaces XAMPP's unsecured default — the `root` account with a blank
password. Evidence for **Appendix F**:

```powershell
C:\xampp\mysql\bin\mysql.exe --user=root --host=127.0.0.1 --protocol=TCP `
  --execute="SHOW GRANTS FOR 'inventory_app'@'localhost';"
```

### 1.4 Load the sample data

Open the URL printed by the setup script:

```
http://localhost/inventory/install.php?token=<INSTALL_TOKEN from .env>
```

It loads five categories and twelve demonstration products, then creates the `admin`
account with a randomly generated password that is **displayed once**. Save it, then
change it from the **My account** page.

### 1.5 Verify through localhost

Open `http://localhost/inventory/` and sign in.

> Screenshots for **Appendix A, Figures A2–A4**: the login page, the dashboard and the
> product list.

### 1.6 Find your local IP address

```powershell
ipconfig
```

The Wi-Fi adapter's IPv4 address was **10.235.219.205** when this was set up. It changes
when you reconnect, so check it again on the day.

> Screenshot the `ipconfig` output for **Appendix B, Figure B1**.

### 1.7 Allow other devices through Windows Firewall

Apache already listens on every interface, but Windows Firewall blocks inbound port 80 by
default. Open **PowerShell as Administrator** (right-click → Run as administrator) and run:

```powershell
New-NetFirewallRule -DisplayName "XAMPP Apache HTTP 80 (BIT3253 local test)" `
  -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -Profile Private
```

`-Profile Private` limits this to networks marked Private — your home Wi-Fi — and not to
public networks. Remove it when you have finished the assessment:

```powershell
Remove-NetFirewallRule -DisplayName "XAMPP Apache HTTP 80 (BIT3253 local test)"
```

### 1.8 Test from another device

Connect a phone or a second laptop to the **same Wi-Fi network** and browse to:

```
http://10.235.219.205/inventory/
```

> Photograph or screenshot the phone showing the application, with the IP address visible
> in the address bar, for **Appendix B, Figure B2**. This is the evidence that the local
> deployment is reachable across the LAN but not from the internet.

---

## Part 2 — Cloud infrastructure (Task C)

### 2.1 Push the repository to GitHub

A local Git repository already exists with the first commit. Create an **empty** repository
on GitHub (no README, no .gitignore), then:

```powershell
git remote add origin https://github.com/<your-username>/inventory-system.git
git push -u origin main
```

Confirm that `.env` is **not** in the pushed files. It is listed in `.gitignore`; the
repository contains `.env.example` with placeholder values only.

> Screenshot the GitHub repository page for **Appendix C, Figure C1**.

### 2.2 Create the Render services

1. Sign in at <https://render.com> with your GitHub account.
2. **New → Blueprint**, select the repository, and Render reads `render.yaml`.
3. It proposes a web service (`inventory-system`) and a PostgreSQL database
   (`inventory-db`). Change `region` in `render.yaml` first if Singapore is not nearest to you.
4. **Apply**. Render builds the Docker image and starts both services.

The blueprint wires the database connection string into the application as `DATABASE_URL`
and generates a random `INSTALL_TOKEN`, so no credential is ever typed by hand or stored
in the repository.

> Screenshot the build log and the "Live" status for **Appendix C, Figures C2–C3**.

If you prefer to do it manually instead of with the blueprint: create the PostgreSQL
database first, then **New → Web Service → Docker**, and add the environment variables
`APP_ENV=production`, `DATABASE_URL` (from the database's *Internal* connection string)
and `INSTALL_TOKEN` (any long random string).

### 2.3 Initialise the cloud database

Copy `INSTALL_TOKEN` from the service's **Environment** tab and open once:

```
https://<your-service>.onrender.com/install.php?token=<INSTALL_TOKEN>
```

The same installer runs, this time creating the PostgreSQL schema. It prints a separate
administrator password for the cloud deployment. Save it and change it after signing in.

### 2.4 Verify the public URL

Open `https://<your-service>.onrender.com/` and sign in.

> **Appendix C, Figure C4**: the application running at the public HTTPS URL, with the
> padlock and full URL visible. Record this URL in section 2 of the report — it is
> explicitly marked for marks.

Check `https://<your-service>.onrender.com/health.php` too; it should report
`"environment": "production"` and `"database": "ok"`.

---

## Part 3 — Collecting the evidence

### 3.1 Local system resources (Appendix D)

```powershell
Get-CimInstance Win32_Processor |
  Select-Object Name, NumberOfCores, NumberOfLogicalProcessors, MaxClockSpeed
[math]::Round((Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory/1GB, 2)
```

Measured on this machine: **Intel Celeron N4000 @ 1.10 GHz, 2 cores / 2 threads, 7.83 GB RAM**,
Windows 11 Home Single Language. Also screenshot **Task Manager → Performance** while the
application is being used.

### 3.2 Cloud resources (Appendix D)

Screenshot the Render service's **Settings → Instance Type** page showing the Free plan's
512 MB RAM and 0.1 CPU, and the database's plan page showing its 1 GB storage.

### 3.3 Response times (Appendix E)

Browser method, required by the assessment:

1. Open DevTools (**F12**) → **Network** tab, tick **Disable cache**.
2. Load `http://localhost/inventory/index.php?page=dashboard`, screenshot the waterfall and
   the Load / DOMContentLoaded figures.
3. Repeat on the public cloud URL.
4. Take a second cloud measurement **after the service has been idle for 15+ minutes** to
   capture a cold start. This is the single most interesting number in the comparison.

Script method, for a larger sample:

```powershell
powershell -ExecutionPolicy Bypass -File tools\measure_performance.ps1 `
  -LocalUrl "http://localhost/inventory/health.php" `
  -CloudUrl "https://<your-service>.onrender.com/health.php" `
  -Runs 15 -CsvPath docs\performance.csv
```

It reports min, mean, median, 95th percentile and max, and keeps the first request
separate so a cold start does not distort the average.

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

The container writes Apache's access and error logs to stdout and stderr, so they appear
in Render's live log viewer. Screenshot:

- **Logs** tab showing real requests, including the spin-down and restart messages
- **Metrics** tab showing CPU and memory usage
- **Events** tab showing the deployment history

Make a few requests first so there is something to show.

### 3.5 Security and database (Appendix F)

- phpMyAdmin showing `inventory_db` and its four tables
- The `SHOW GRANTS` output from step 1.3
- A browser request to `http://localhost/inventory/src/db.php` returning **403 Forbidden**
- The activity log page showing recorded sign-ins and stock changes
- A backup produced by `tools\backup_local.ps1`

Do **not** screenshot `.env`, the `users` table, or any installer output containing a password.

### 3.6 Cost evidence (Appendix G)

Screenshot <https://render.com/pricing> and the free-tier documentation at
<https://render.com/docs/free>. Prices change, so capture them on the day you write the
report rather than relying on the figures in the template.

---

## Troubleshooting

**Apache will not start — port 80 in use.** Usually IIS, Skype or another server. Find it
with `Get-NetTCPConnection -State Listen -LocalPort 80`, or change Apache's port to 8080 in
`C:\xampp\apache\conf\httpd.conf` and use `http://localhost:8080/inventory/`.

**"Database not initialised".** MySQL is not running, or `.env` has the wrong credentials.
Check `http://localhost/inventory/health.php` for the specific fault.

**Access denied for the application account.** Re-run `tools\setup_local.ps1`. If the GRANT
does not stick, the MariaDB privilege table may be corrupt — that happened on this machine
and was repaired with:

```powershell
# stop MySQL first
C:\xampp\mysql\bin\aria_chk.exe --recover --force C:\xampp\mysql\data\mysql\db
```

**Lost the administrator password.**

```powershell
C:\xampp\php\php.exe tools\set_password.php admin <new-password>
```

**Cloud build fails on `entrypoint.sh`.** The file must keep LF line endings;
`.gitattributes` enforces this. Confirm with `git check-attr -a docker/entrypoint.sh`.

**The cloud site is very slow on the first visit.** Expected: the free instance spins down
after 15 minutes of inactivity and takes roughly 30–60 seconds to start again. Document it
rather than working around it — it is the clearest limitation of the free tier.
