# ⚙️ FlowForge

A no-framework **PHP** engine that turns incoming events (webhooks, form posts,
CRM/billing notifications) into automated workflows: **match → transform → dispatch**.
It's the "build customised integrations to automate workflows" brief, built as a
small, dependency-free, deploy-anywhere PHP app.

### 🔗 [Live demo →](https://hritishmahajan.github.io/flowforge-php/)
The live link runs the dashboard with an embedded JS engine that mirrors the PHP
core (GitHub Pages can't execute PHP). Run it locally for the **real PHP REST API** —
the dashboard auto-detects the backend and flips to LIVE mode.

---

## Why PHP
- **Zero dependencies, zero build.** No Composer, no node_modules. Clone and run.
- **Built-in web server** — `php -S localhost:8000` and the whole app (API + UI) is up.
- **Runs on any shared host** — the natural home for integration glue that has to
  live somewhere cheap and always-on.
- **PDO + prepared statements** for safe MySQL, with a zero-config JSON fallback so
  the demo needs no database at all.

## Run it (real PHP backend, 1 command)
```bash
php -S localhost:8000        # open http://localhost:8000  → badge shows "LIVE"
```

Seed it from Python over the REST API (cross-language integration demo):
```bash
python3 tools/seed.py
```

Switch to MySQL (optional):
```bash
mysql -u root -p < schema.sql
export FLOWFORGE_DSN="mysql:host=127.0.0.1;dbname=flowforge;charset=utf8mb4"
export FLOWFORGE_DB_USER=root FLOWFORGE_DB_PASS=secret
php -S localhost:8000        # badge now shows "mysql storage"
```

## REST API
| Method | Endpoint          | Purpose                                    |
|--------|-------------------|--------------------------------------------|
| GET    | `/api/health`     | Backend + storage info                     |
| GET    | `/api/workflows`  | List workflows                             |
| POST   | `/api/workflows`  | Create a workflow                          |
| POST   | `/api/events`     | **Ingest an event** → run matching workflows |
| GET    | `/api/runs`       | Execution log                              |
| DELETE | `/api/reset`      | Clear all data                             |

```bash
curl -X POST localhost:8000/api/events \
  -H 'Content-Type: application/json' \
  -d '{"type":"payment.succeeded","data":{"amount":4200,"currency":"EUR"}}'
```

## How a workflow works
```
trigger      payment.succeeded            # the event type it listens for
conditions   amount > 1000  (AND ...)     # rules over the payload (eq/neq/gt/lt/contains)
actions      transform → webhook → tag    # enrich, POST downstream, label
```

## Layout
```
index.php        single-file REST API + static server + router
lib/Engine.php   the automation core: match → transform → dispatch
lib/Storage.php  JSON backend (default) + MySQL/PDO backend (same interface)
docs/            the dashboard (also the GitHub Pages live demo)
tools/seed.py    Python client that drives the API
schema.sql       MySQL schema
```

## Stack
PHP 8.4 · REST · MySQL (PDO) · vanilla JS/HTML/CSS frontend · Python tooling · Git

Built by **Hritish Mahajan**.
