#!/usr/bin/env python3
"""gen-postman.py — generate Postman Collection v2.1 dari OpenAPI (stdlib only).

Penggunaan:
  python3 scripts/gen-postman.py <nama> <url-openapi>
  contoh: python3 scripts/gen-postman.py omc http://localhost:8100/openapi.json

Output: docs/api/postman/<nama>.postman_collection.json
"""
from __future__ import annotations

import json
import sys
import urllib.request
from pathlib import Path

OUT_DIR = Path(__file__).resolve().parent.parent / "docs" / "api" / "postman"


def main() -> None:
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    name, url = sys.argv[1], sys.argv[2]

    with urllib.request.urlopen(url, timeout=15) as r:
        spec = json.load(r)

    items: list[dict] = []
    for path, methods in spec.get("paths", {}).items():
        for method in ("get", "post", "put", "patch", "delete"):
            op = methods.get(method)
            if not op:
                continue
            # {x} → :x (Postman variable); pakai replace sederhana
            postman_path = path.replace("{", ":").replace("}", "")
            items.append({
                "name": op.get("summary", f"{method.upper()} {path}"),
                "request": {
                    "method": method.upper(),
                    "url": {"raw": f"{{base_url}}{postman_path}",
                            "host": ["{{base_url}}"],
                            "path": [f"{{base_url}}"] + [p for p in postman_path.split("/") if p]},
                    "header": [],
                    "description": op.get("description", ""),
                },
            })

    collection = {
        "info": {
            "name": f"ORP {name}",
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "variable": [{"key": "base_url", "value": "http://localhost:8000", "type": "string"}],
        "item": items,
    }

    out = OUT_DIR / f"{name}.postman-collection.json"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(collection, indent=2, ensure_ascii=False))
    print(f"OK → {out} ({len(items)} endpoint)")


if __name__ == "__main__":
    main()