#!/usr/bin/env python3
"""
Seed FlowForge over its REST API — demonstrates a Python ↔ PHP integration,
exactly the kind of cross-language automation glue the role calls for.

Usage:
    php -S localhost:8000        # start the PHP backend in another terminal
    python3 tools/seed.py        # creates a workflow, fires sample events
"""
import json
import urllib.request

BASE = "http://localhost:8000/api"


def post(path: str, body: dict) -> dict:
    req = urllib.request.Request(
        f"{BASE}{path}",
        data=json.dumps(body).encode(),
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read())


def main() -> None:
    post("/workflows", {
        "name": "Welcome new signups",
        "trigger": "user.signup",
        "conditions": [{"field": "plan", "op": "eq", "value": "pro"}],
        "actions": [
            {"type": "transform", "field": "status", "value": "onboarding"},
            {"type": "webhook", "url": "https://httpbin.org/post"},
            {"type": "tag", "label": "pro-trial"},
        ],
    })

    events = [
        {"type": "user.signup", "data": {"email": "sam@acme.io", "plan": "pro"}},
        {"type": "user.signup", "data": {"email": "lee@acme.io", "plan": "free"}},
        {"type": "payment.succeeded", "data": {"amount": 5000, "currency": "EUR"}},
    ]
    for ev in events:
        res = post("/events", ev)
        print(f"{ev['type']:<20} → matched {res['matched']} workflow(s)")


if __name__ == "__main__":
    main()
