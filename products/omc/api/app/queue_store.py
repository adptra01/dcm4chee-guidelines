"""Antrean studi persisten (SQLite stdlib) — survive restart service.

Tabel: study_id, metadata JSON, path, stored (0/1). DB di data/queue.db.
Koneksi SQLite dibuka-ditutup per operasi (hindari lock).
"""
from __future__ import annotations

import json
import sqlite3
from pathlib import Path

DB = Path(__file__).parent.parent / "data" / "queue.db"


def _conn() -> sqlite3.Connection:
    conn = sqlite3.connect(DB)
    conn.execute("""CREATE TABLE IF NOT EXISTS studies (
        study_id TEXT PRIMARY KEY,
        metadata TEXT NOT NULL,
        path TEXT NOT NULL,
        stored INTEGER NOT NULL DEFAULT 0
    )""")
    return conn


def _run(sql: str, params: tuple = ()) -> sqlite3.Cursor:
    conn = _conn()
    try:
        cur = conn.execute(sql, params)
        conn.commit()
        return cur
    finally:
        conn.close()


def _fetchall(sql: str, params: tuple = ()) -> list[tuple]:
    conn = _conn()
    try:
        return conn.execute(sql, params).fetchall()
    finally:
        conn.close()


def insert(study_id: str, metadata: dict, path: str) -> None:
    _run("INSERT INTO studies VALUES (?, ?, ?, 0)",
         (study_id, json.dumps(metadata), path))


def list_all() -> list[dict]:
    return [
        {"study_id": sid, "metadata": json.loads(meta), "path": p, "stored": bool(st)}
        for sid, meta, p, st in _fetchall("SELECT study_id, metadata, path, stored FROM studies")
    ]


def get(study_id: str) -> dict | None:
    row = _fetchall("SELECT study_id, metadata, path, stored FROM studies WHERE study_id = ?",
                    (study_id,))
    if not row:
        return None
    sid, meta, p, st = row[0]
    return {"study_id": sid, "metadata": json.loads(meta), "path": p, "stored": bool(st)}


def mark_stored(study_id: str) -> None:
    _run("UPDATE studies SET stored = 1 WHERE study_id = ?", (study_id,))
