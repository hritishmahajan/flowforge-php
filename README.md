# FlowForge

**A no-framework PHP engine that automates workflows from incoming events.**
An event comes in (a payment, a signup, an order) → FlowForge checks it against
rules you defined → and automatically does things: tag it, transform it, or POST
it onward to another system. That's the "build custom integrations to automate
workflows" job, built as a small, dependency-free PHP app.

### Live demo → https://hritishmahajan.github.io/flowforge-php/

---

## What is this, in plain English?

Imagine a tireless assistant watching everything happening in your business:

> "Whenever a **payment over €1000** comes in, label it **high-value** and notify our finance system."

You write that rule once. From then on, every matching event is handled
automatically — no manual work. FlowForge is the engine that does the watching,
matching, and acting. A workflow has three parts:

| Part | Meaning | Example |
|------|---------|---------|
| **Trigger** | which event to listen for | `payment.succeeded` |
| **Condition** | a rule on the event's data | `amount > 1000` |
| **Action** | what to do when it matches | tag it, POST to a webhook |

---

## Try it live in 30 seconds (no install)

Open the [live demo](https://hritishmahajan.github.io/flowforge-php/) and:

1. **Create a workflow** — left panel. Pick a trigger (`payment.succeeded`),
   set a condition (`amount > 1000`), add an action, then **Create workflow**.
2. **Fire a test event** — right panel. Edit the JSON payload and click **Ingest event**.
3. **Watch the Run log** at the bottom — you'll see which workflows matched and ran.

> The live link runs a small JavaScript engine that mirrors the PHP core
> (GitHub Pages can't execute PHP). Run it locally below for the **real PHP REST API** —
> the badge at the top flips to **LIVE** automatically.

---

## Run the real PHP backend (1 command)

You only need PHP installed. No Composer, no build step.

```bash
git clone https://github.com/hritishmahajan/flowforge-php.git
cd flowforge-php
php -S localhost:8000        # then open http://localhost:8000
```

The badge now reads **LIVE · PHP 8.x · json storage** — every action is real
(webhooks are actually sent).

**Optional — drive it from Python** (a cross-language integration demo):
```bash
python3 tools/seed.py        # creates a workflow and fires sample events
```

**Optional — use MySQL instead of the default JSON file storage:**
```bash
mysql -u root -p < schema.sql
export FLOWFORGE_DSN="mysql:host=127.0.0.1;dbname=flowforge;charset=utf8mb4"
export FLOWFORGE_DB_USER=root FLOWFORGE_DB_PASS=secret
php -S localhost:8000        # badge now shows "mysql storage"
```

---

## REST API

| Method | Endpoint          | Purpose                                        |
|--------|-------------------|------------------------------------------------|
| GET    | `/api/health`     | Backend + storage info                         |
| GET    | `/api/workflows`  | List workflows                                 |
| POST   | `/api/workflows`  | Create a workflow                              |
| POST   | `/api/events`     | **Ingest an event** → run any matching workflows |
| GET    | `/api/runs`       | Execution log                                  |
| DELETE | `/api/reset`      | Clear all data                                 |

```bash
curl -X POST localhost:8000/api/events \
  -H 'Content-Type: application/json' \
  -d '{"type":"payment.succeeded","data":{"amount":4200,"currency":"EUR"}}'
```

---

## Why PHP

- **Zero dependencies, zero build** — clone and run. No Composer, no `node_modules`.
- **Built-in web server** — `php -S` serves the API *and* the dashboard from one file.
- **Runs on any shared host** — the natural home for always-on integration glue.
- **PDO + prepared statements** for safe MySQL, with a JSON fallback so the demo
  needs no database at all.

## Project layout

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
