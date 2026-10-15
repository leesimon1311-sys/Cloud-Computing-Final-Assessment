# Inventory Management System

A web-based inventory management system built for **BIT3253 Cloud Computing**, deployed
to two different infrastructure environments and compared across deployment, performance,
scalability, security, cost, maintenance and reliability.

| | Local environment | Cloud environment |
|---|---|---|
| Platform | XAMPP on Windows 11 | Render (Docker) |
| Web server | Apache 2.4.58 (Win64) | Apache 2.4 (Debian container) |
| Runtime | PHP 8.2.12 | PHP 8.2 |
| Database | MariaDB 10.4.32 | Managed PostgreSQL |
| Access | `http://localhost/inventory/` and LAN IP | Public HTTPS URL |

The identical PHP codebase runs in both environments. The data layer uses PDO and is
written so the same SQL is accepted by MySQL/MariaDB and by PostgreSQL; the engine is
selected from the environment configuration at runtime, never from the application code.

## Origin

The system began as a Java console application (`InventorySystem.java`) with a
`Product` / `PerishableProduct` class hierarchy and an `InventoryManager` that
serialised a `List<Product>` to a local `inventory.dat` file.

That design cannot be deployed to a web server or shared between users, so it was
re-implemented for the web. The domain model carried over directly:

| Console prototype | Web application |
|---|---|
| `Product` / `PerishableProduct` | `products.type` of `standard` or `perishable`, with `expiry_date` |
| `addItem()` / `viewInventory()` / `updateItem()` / `deleteItem()` / `searchItem()` | Create, list, edit, delete and search pages |
| "Price and quantity must be non-negative" | Server-side validation in `product_validate()` |
| `ObjectOutputStream` to `inventory.dat` | Relational database accessed through prepared statements |
| Single user at one keyboard | Multi-user, authenticated, with an audit trail |

## Features

- Username and password authentication (bcrypt hashes, never plain text)
- Dashboard: stock value, low-stock, out-of-stock, expiring and expired counts
- Product management with search, category and status filters, and pagination
- Separate handling of perishable stock, with expiry warnings at 30 days
- One-click stock adjustment
- Activity log recording every sign-in attempt and stock change
- CSV export for offline backup
- `/health.php` JSON endpoint for platform health checks and response-time measurement

## Security

- Every database statement is a prepared statement with bound parameters
- All output is escaped with `htmlspecialchars()`
- CSRF tokens on every state-changing form, verified with `hash_equals()`
- Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` whenever the request is HTTPS
- Session id regenerated on login to defeat session fixation
- Login lockout after 5 failed attempts
- `Content-Security-Policy`, `X-Frame-Options` and `X-Content-Type-Options` response headers
- `src/`, `views/`, `sql/` and `tools/` are outside the document root in the container, and
  denied by `.htaccess` under XAMPP
- The application connects through a least-privilege database account that holds only
  `SELECT`, `INSERT`, `UPDATE` and `DELETE` — never the MySQL `root` account
- Credentials live only in `.env`, which is excluded from version control

## Repository layout

```
public/          Document root: front controller, health check, installer, assets
src/             Configuration, database layer, authentication, domain queries
views/           Templates
sql/             Schema for each engine, plus the local access-control script
docker/          Container configuration for the cloud deployment
tools/           Setup, backup, password reset and performance measurement scripts
docs/            Deployment guide and assessment working documents
Dockerfile       Cloud image: Apache + PHP 8.2 + pdo_mysql + pdo_pgsql
render.yaml      Infrastructure as code: web service and managed database
```

## Getting started

Local setup, cloud deployment and evidence collection are documented in
[docs/DEPLOYMENT_GUIDE.md](docs/DEPLOYMENT_GUIDE.md).

```powershell
powershell -ExecutionPolicy Bypass -File tools\setup_local.ps1
```

Then open the installer URL that the script prints, save the administrator password it
generates, and sign in at `http://localhost/inventory/`.
