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

- [ ] **F1** `SHOW GRANTS FOR 'inventory_app'@'localhost'` output
- [ ] **F2** Browser showing **403 Forbidden** for `http://localhost/inventory/src/db.php`
- [ ] **F3** The application's Activity Log page with sign-ins and stock changes recorded
- [ ] **F4** DevTools Network → Headers showing `Content-Security-Policy` and
      `X-Frame-Options` on a response
- [ ] **F5** Terminal output of `tools\backup_local.ps1` with the dump file and size
- [ ] **F6** `sql/schema_mysql.sql` or a phpMyAdmin structure view of the tables

**Never capture:** `.env` contents, the `users` table, installer output containing a
password, Render connection strings, or the `INSTALL_TOKEN` value.

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
