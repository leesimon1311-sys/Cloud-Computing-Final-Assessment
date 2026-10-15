# Report Outline and Source Data

**This is a working document, not the report.** It gives you the section structure, the
verified technical facts gathered from the two deployments, and a note on what each
section has to demonstrate. The analysis, the argument and the writing are yours — those
are what is actually being marked.

Replace every `[...]` with your own figures and words. Anything marked **verify** must be
re-checked on the day you write, because it changes.

---

## Verified facts from this build

Collected from the running deployments. Use these rather than inventing numbers.

### Local environment

| Item | Value |
|---|---|
| CPU | Intel Celeron N4000 @ 1.10 GHz |
| Cores / threads | 2 / 2 |
| RAM | 7.83 GB |
| Operating system | Windows 11 Home Single Language (build 10.0.26300) |
| Web server | Apache 2.4.58 (Win64), Apache Lounge VS17 |
| PHP | 8.2.12 |
| Database | MariaDB 10.4.32 |
| Stack installed via | XAMPP |
| Database administration | phpMyAdmin 5.x (bundled with XAMPP) |
| Local URL | `http://localhost/inventory/` |
| LAN URL | `http://10.235.219.205/inventory/` *(re-check, it changes)* |
| Database account | `inventory_app@localhost`, granted only SELECT/INSERT/UPDATE/DELETE on `inventory_db` |

### Database hardening performed (section 5 material)

XAMPP's defaults were replaced. Each row is a real change you made, with the attack it
prevents — this is far stronger than generic security theory:

| Default in XAMPP | Changed to | What it prevents |
|---|---|---|
| `root` with no password on 3 host entries | Strong password on each | Total database takeover by anyone reaching the machine |
| phpMyAdmin `auth_type = 'config'` | `'cookie'` | phpMyAdmin signing in as root automatically, with no login at all |
| `AllowNoPassword = true` | `false` | Password-less sign in |
| `blowfish_secret = 'xampp'` | Random 32-character secret | Forged or decrypted phpMyAdmin session cookies |
| `pma` control account with no password | Generated password, scoped to `phpmyadmin` only | A second unprotected account |
| Wildcard grants on the `test` database | Removed, database dropped | Any account reading and writing `test` |
| Application connecting as `root` | `inventory_app` with 4 privileges on 1 database | SQL injection or a code flaw being able to DROP tables or read other databases |

Application-level controls, all implemented in code: prepared statements with bound
parameters, bcrypt password hashing, CSRF tokens verified with `hash_equals()`, output
escaping, `HttpOnly`/`SameSite`/`Secure` session cookies, session id regeneration on login,
login lockout after 5 failed attempts, CSP and `X-Frame-Options` headers, and `.htaccess`
denial of `src/`, `views/`, `sql/` and `tools/`.

Verified by test: 53 automated checks on the application and 12 on phpMyAdmin, including
that a blank password is refused and that `src/db.php` returns 403.

### Cloud environment

| Item | Value |
|---|---|
| Provider | Render |
| Deployment method | Docker image built from `Dockerfile`, defined in `render.yaml` |
| Base image | `php:8.2-apache` (Debian) |
| Web plan | Free — 512 MB RAM, 0.1 CPU **(verify)** |
| Database | Managed PostgreSQL, Free — 1 GB storage **(verify)** |
| Spin-down | After 15 minutes without inbound traffic **(verify)** |
| Cold start | Roughly 30–60 seconds **(measure it yourself)** |
| Free instance hours | 750 per workspace per month **(verify)** |
| Free database expiry | 30 days after creation, 14-day grace period to upgrade **(verify)** |
| Public URL | `[your-service].onrender.com` |

### Measured local performance

12 requests to `health.php` (157-byte JSON response), measured with
`tools\measure_performance.ps1`:

| Metric | Local |
|---|---|
| First request | 105.4 ms |
| Minimum | 33.5 ms |
| Mean | 76.2 ms |
| Median | 71.9 ms |
| 95th percentile | 111.9 ms |
| Maximum | 111.9 ms |

You still need the cloud column, plus a browser DevTools measurement of the full dashboard
page in both environments.

### Database schema

Four tables: `users`, `categories`, `products`, `activity_log`. One foreign key
(`products.category_id → categories.id`, `ON DELETE SET NULL`). Indexes on
`products.name`, `products.quantity`, `products.expiry_date` and `activity_log.created_at`.

### Application functions

Authentication with lockout · dashboard with stock valuation and alerts · product create,
read, update, delete · search by name or SKU · filter by category and by stock/expiry
status · pagination · one-click stock adjustment · separate perishable handling with
30-day expiry warnings · CSV export · activity log · health check endpoint.

---

## Section 1 — Introduction and Application Scenario (10 marks)

Cover: background of the scenario; the problem it addresses; **three** clear objectives;
purpose and scope; the technologies and the two environments.

Your three objectives must be specific enough to be judged achieved or not in section 7.
"To make a good system" cannot be assessed. Something closer to: *to replace single-user
file storage with a multi-user database accessible over a network*, *to give staff an
automatic low-stock and expiry warning instead of manual checking*, *to compare the cost
and performance of local and cloud hosting for a small retail business*.

Scope matters too — say what is **not** included (no purchase orders, no supplier
management, no barcode scanning), because an unbounded scope is impossible to evaluate.

`[Write 400–600 words]`

## Section 2 — Application and Infrastructure Deployment (15 marks)

The largest single section. Cover: the application and its main functions; local setup and
configuration; testing through `localhost` and through the local IP; cloud deployment
process and configuration; **the working public URL**; and a comparison of the two
deployment processes by ease, speed and configuration requirements.

The deployment-process comparison is where the marks concentrate. Concrete contrasts worth
making, all of which you performed:

- Local required installing XAMPP, starting two services, creating a database, creating a
  database account, granting privileges, importing a schema and placing files in the
  document root. Cloud required pushing to GitHub and applying one blueprint file.
- Local configuration is manual and machine-specific; the cloud configuration is
  `render.yaml`, which is version-controlled and reproducible.
- The local deployment produced no credentials management problem because nothing leaves
  the machine; the cloud deployment required secrets to be injected by the platform.
- Updating locally means editing files in place. Updating in the cloud means a commit,
  which triggers an automatic rebuild and redeploy.
- Local gave an immediately working site with no waiting; the first cloud deploy took
  `[time it]` to build the image.

Record the time each process took. Timings are evidence; impressions are not.

`[Write 600–900 words, referring to Appendices A, B and C]`

## Section 3 — Infrastructure Comparison and Resource Evaluation (10 marks)

Cover: local hardware and system resources; cloud resources; **a comparison table**;
resource limitations of both; differences in infrastructure requirements and configuration
complexity; and which environment provides more suitable resources, justified.

A table is explicitly requested — the figures above fill it. The interesting observation
available to you: the local machine has roughly 16× the RAM of the free cloud instance and
a far stronger CPU allocation, yet the cloud deployment is the one that is continuously
available and reachable from anywhere. Resource quantity and service quality are different
things, and saying so is worth more than reciting the specifications.

`[Write 400–600 words with the comparison table, referring to Appendix D]`

## Section 4 — Performance and Scalability Analysis (10 marks)

Cover: measurement method; results for both; analysis of the differences and why; how
local could be upgraded for more users; how the cloud could be scaled; cloud monitoring,
logs or metrics; and **at least one** major limitation for each environment.

Explain the method properly — how many requests, cache disabled, which page, measured
from where. A single screenshot of one page load is a weak result; a table of repeated
measurements with a mean is a strong one.

When analysing the difference, note the obvious cause: the local request never leaves the
machine, whereas the cloud request crosses the internet to the chosen region. That is
network latency, not slower computation. The cold start is a different phenomenon
altogether — the container is not running at all and must be started — and it is worth
separating the two in your analysis.

Candidate limitations:
- **Local:** 2 CPU cores shared with the operating system; the machine must stay switched
  on and connected; a residential connection's upload speed caps concurrent users; no
  redundancy of any kind.
- **Cloud:** the free instance spins down after 15 minutes, so the first visitor after a
  quiet period waits roughly 30–60 seconds; 0.1 CPU is a small share; the free database
  expires after 30 days.

`[Write 500–800 words with results tables, referring to Appendix E]`

## Section 5 — Security, Maintenance and Reliability Analysis (10 marks)

Cover: how the application and database are protected in each environment; the shared
responsibility comparison; main risks and weaknesses of both; backup, maintenance, update
and recovery; reliability evaluation with major risks; and how all three could be improved.

You have real material here rather than generic theory, because you implemented it — the
hardening table near the top of this document lists every XAMPP default you replaced and
the attack each change prevents. Work through it and describe what each one defends
against, rather than listing security features in the abstract.

The phpMyAdmin login screen is worth calling out explicitly: a stock XAMPP install signs
in as root automatically and never shows one, so its existence is itself the evidence that
the default configuration was replaced.

For shared responsibility, the honest split: the provider secures the physical hardware,
the hypervisor, the host operating system, the network and the managed database engine,
and supplies TLS. You remain responsible for application code, authentication, access
control, dependency updates and your own data. Locally, every one of those layers is
yours — including physical security of the machine, which is rarely mentioned and is a
genuine difference.

Reliability contrast worth making: the local deployment has a single point of failure in
every layer at once — one disk, one power supply, one internet connection, no failover.
The cloud deployment survives hardware failure but depends entirely on one provider and
one account.

Backups: local backups are manual (`tools\backup_local.ps1`) and, if they stay on the same
machine, protect against nothing but accidental deletion. Managed cloud databases are
backed up automatically — state what the plan you chose actually includes, and check it.

**Do not put real passwords, tokens, connection strings or credentials in the report or in
any screenshot.** The question paper flags this explicitly.

`[Write 600–900 words, referring to Appendix F]`

## Section 6 — Cost and Financial Comparison (10 marks)

Cover: local costs (hardware, electricity, network, maintenance, upgrades); cloud costs
(free-tier limits and paid plans); a comparison; and analysis of the financial implications.

Build the local figure honestly. A worked method, with your own numbers:

- Hardware: purchase price ÷ expected service life in months
- Electricity: device wattage × 24 h × 30 days ÷ 1000 × tariff per kWh. Check the current
  domestic tariff for your area and cite it.
- Internet: the share of the monthly bill attributable to hosting, or note that it is a
  cost already being paid
- Maintenance: your own time, valued at some rate, or an annual servicing cost

Cloud figures to verify on the pricing page on the day:
- Free: $0, with the spin-down, 750 instance-hours and 30-day database expiry as the price
- Starter web instance: $7/month, 512 MB RAM, 0.5 CPU, always on **(verify)**
- Standard web instance: $25/month, 2 GB RAM, 1 CPU **(verify)**
- Basic-256mb database: $6/month; Basic-1gb: $19/month **(verify)**

The conclusion most students miss: the free tier is not free of cost, it is free of
*money*. It costs availability and a database that expires. Saying this explicitly, and
pricing the realistic always-on configuration, is a stronger answer than a table showing
$0.

`[Write 400–600 words with a cost table, referring to Appendix G]`

## Section 7 — Conclusion and Recommendations (5 marks)

Cover: key findings; overall comparison across all six dimensions; **whether your three
objectives were achieved**; the recommended environment; justification from your own
evidence; and practical future improvements.

Answer the objectives from section 1 one at a time and say plainly whether each was met.
Then recommend one environment and defend it with figures you measured, not with general
claims about the cloud. A recommendation that acknowledges a real trade-off — for example
that the local deployment is genuinely faster, and you are recommending the cloud anyway
for availability — reads as analysis rather than as a preference.

`[Write 300–500 words]`

## Section 8 — Appendices and Practical Evidence (5 marks)

See `EVIDENCE_CHECKLIST.md`. Label every figure (Figure A1, A2, …) and refer to each one
from the main text at least once.

## Section 9 — References and Paperwork (5 marks)

APA 7th edition, minimum five credible sources, every listed reference cited in the text.
Cover page, table of contents, headings, page numbers. Number and caption all tables and
figures.

Sources that genuinely support this report — read them, cite what you use:

- Render documentation on free-tier limits: <https://render.com/docs/free>
- Render pricing: <https://render.com/pricing>
- PHP manual, prepared statements: <https://www.php.net/manual/en/pdo.prepared-statements.php>
- OWASP Top Ten: <https://owasp.org/www-project-top-ten/>
- NIST Definition of Cloud Computing (SP 800-145) — for the service-model definitions
- The Apache HTTP Server and MariaDB documentation for the local stack

Mix vendor documentation with at least one or two academic or standards sources; a
reference list made only of blog posts tends to lose marks.
