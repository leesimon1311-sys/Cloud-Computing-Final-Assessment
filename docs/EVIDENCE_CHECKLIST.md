# Evidence Checklist (Appendices, 5 marks)

Tick each item as you capture it. Label every image **Figure A1, A2, B1, …** and refer to
it at least once from the main report — unreferenced screenshots earn nothing.

Crop tightly, zoom the browser to 110–125% before capturing so text stays readable when
printed, and avoid including your whole desktop.

---

## Appendix A — Local Infrastructure

- [ ] **A1** XAMPP Control Panel with Apache and MySQL running (green, PIDs and ports visible)
- [ ] **A2** `http://localhost/inventory/` login page, URL bar visible
- [ ] **A3** Dashboard signed in, showing the stock value and alert tiles
- [ ] **A4** Product list showing search and filters in use
- [ ] **A5** Add/edit product form, ideally showing a validation message
- [ ] **A6** phpMyAdmin showing `inventory_db` and its four tables

## Appendix B — Local Network Access

- [ ] **B1** `ipconfig` output with the IPv4 address visible
- [ ] **B2** The application open **on another device** (phone or second laptop) with the
      `http://<your-ip>/inventory/` URL visible in its address bar
- [ ] **B3** *(optional, strong evidence)* The same device failing to reach the address
      over mobile data instead of Wi-Fi — proves the local deployment is LAN-only

## Appendix C — Cloud Deployment

- [ ] **C1** GitHub repository page showing the committed code
- [ ] **C2** Render build log for a successful Docker build
- [ ] **C3** Render dashboard showing the service **Live** and the database **Available**
- [ ] **C4** The application running at the **public HTTPS URL**, padlock and full URL visible
- [ ] **C5** `health.php` on the cloud URL returning `"environment": "production"`
- [ ] **C6** The Environment Variables tab — **blur or crop every value**, show only the keys

## Appendix D — System Resources

- [ ] **D1** Local CPU and RAM (`Get-CimInstance` output or System Information)
- [ ] **D2** Task Manager → Performance while using the application
- [ ] **D3** Render instance type page showing the free plan's RAM and CPU
- [ ] **D4** Render database page showing its storage allocation

## Appendix E — Performance and Monitoring

- [ ] **E1** DevTools Network tab, local dashboard load, cache disabled
- [ ] **E2** DevTools Network tab, cloud dashboard load, cache disabled
- [ ] **E3** DevTools Network tab, cloud **cold start** after 15+ minutes idle
- [ ] **E4** `measure_performance.ps1` summary table for both environments
- [ ] **E5** Render **Logs** tab showing real requests
- [ ] **E6** Render **Metrics** tab showing CPU and memory
- [ ] **E7** Render **Events** tab showing deploy history and any spin-down/restart

## Appendix F — Security and Database

Most of this is phpMyAdmin. These are the strongest marks-per-screenshot in the whole
appendix, because they directly answer *"avoid using an unsecured default database
configuration"*.

- [ ] **F1** phpMyAdmin **login screen** at `http://localhost/phpmyadmin/` while signed out.
      A stock XAMPP install signs in as root automatically and cannot produce this screen,
      so it is proof on its own that you replaced the default
- [ ] **F2** SQL tab → `SHOW GRANTS FOR 'inventory_app'@'localhost';` showing only
      SELECT/INSERT/UPDATE/DELETE on `inventory_db`, and nothing on `*.*`
- [ ] **F3** `users` table → **Browse**, showing `password_hash` full of `$2y$10$...`
      values. A bcrypt hash is safe to show; the password is not
- [ ] **F4** **User accounts** tab listing `root`, `pma` and `inventory_app` with their
      hosts — shows separate accounts rather than everything running as root
- [ ] **F5** `products` table → **Structure**, showing column types, the three indexes and
      the foreign key to `categories`
- [ ] **F6** `inventory_db` → **Export** tab (SQL format) before pressing Go — the paper
      asks for "SQL export where applicable"
- [ ] **F7** Browser showing **403 Forbidden** for `http://localhost/inventory/src/db.php`
- [ ] **F8** DevTools Network → Headers showing `Content-Security-Policy` and
      `X-Frame-Options` on a response
- [ ] **F9** The application's **Activity Log** page with sign-ins and stock changes recorded
- [ ] **F10** Terminal output of `tools\backup_local.ps1` with the dump file name and size
- [ ] **F11** *(optional, strong)* A before/after of `config.inc.php` — the backed-up
      original with `auth_type = 'config'` beside the hardened `auth_type = 'cookie'`

**Never capture:** `CREDENTIALS-LOCAL.txt`, `.env` contents, the root or `inventory_app`
password, installer output containing a password, Render connection strings, or the
`INSTALL_TOKEN` value. Blur the `controlpass` line if `config.inc.php` appears in F11.

## Appendix G — Cost and Pricing

- [ ] **G1** Render pricing page, captured on the day you write the report
- [ ] **G2** Render free-tier documentation page showing the spin-down and database expiry
- [ ] **G3** Your electricity tariff source
- [ ] **G4** *(optional)* A retail price for comparable local server hardware

---

## Final checks before submitting

- [ ] Every figure is numbered and captioned
- [ ] Every figure is referred to from the main report at least once
- [ ] No password, token, connection string or credential appears anywhere
- [ ] The working public URL appears in section 2
- [ ] Three objectives stated in section 1 are each answered in section 7
- [ ] Minimum five references, all cited in the text, APA 7th edition
- [ ] Cover page, table of contents, page numbers, consistent headings
- [ ] The cloud service is **still live** on the day you submit and present
