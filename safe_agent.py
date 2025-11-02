#!/usr/bin/env python3
from flask import Flask, request, jsonify
import os
import subprocess
import shlex

# ---- CONFIG ----
WORKSPACE_ROOT = os.path.abspath("./workspace")
os.makedirs(WORKSPACE_ROOT, exist_ok=True)

ALLOWED_COMMANDS = {
    "dir": "builtin",
    "type": "builtin",
    "echo": "builtin",
    "python": "exec",
    "pytest": "exec"
}

IS_WINDOWS = os.name == "nt"
app = Flask(__name__)


def safe_path(path: str) -> str:
    full = os.path.abspath(os.path.join(WORKSPACE_ROOT, path))
    if not full.startswith(WORKSPACE_ROOT):
        raise PermissionError("Access outside workspace denied.")
    return full


@app.post("/read")
def read_file():
    try:
        data = request.get_json(force=True)
        rel_path = data.get("path")
        path = safe_path(rel_path)
        if not os.path.isfile(path):
            return jsonify({
                "ok": False,
                "error": f"File not found: {rel_path}"
            }), 404
        with open(path, "r", encoding="utf-8", errors="ignore") as f:
            return jsonify({"ok": True, "path": rel_path, "content": f.read()})
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 400


@app.post("/write")
def write_file():
    try:
        data = request.get_json(force=True)
        rel_path = data.get("path")
        content = data.get("content", "")
        path = safe_path(rel_path)
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write(content)
        return jsonify({"ok": True, "message": f"Wrote {path}"})
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 400


@app.get("/list")
def list_dir():
    try:
        rel_path = request.args.get("path", "")
        path = safe_path(rel_path)
        if not os.path.isdir(path):
            return jsonify({
                "ok": False,
                "error": f"Directory not found: {rel_path}"
            }), 404
        entries = sorted(os.listdir(path))
        return jsonify({"ok": True, "path": rel_path, "entries": entries})
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 400


@app.post("/run")
def run_cmd():
    try:
        data = request.get_json(force=True)
        raw = data.get("cmd", "").strip()
        if not raw:
            return jsonify({
                "ok": False,
                "error": "Missing or invalid 'cmd' string."
            }), 400

        parts = shlex.split(raw)
        verb = parts[0]

        if verb not in ALLOWED_COMMANDS:
            return jsonify({
                "ok": False,
                "error": f"Command '{verb}' not allowed."
            }), 403

        mode = ALLOWED_COMMANDS[verb]

        if mode == "builtin":
            cmd = ["cmd.exe", "/c", raw
                   ] if IS_WINDOWS else ["/bin/sh", "-lc", raw]
        else:
            cmd = parts

        result = subprocess.run(cmd,
                                capture_output=True,
                                text=True,
                                timeout=10,
                                cwd=WORKSPACE_ROOT)

        return jsonify({
            "ok": True,
            "stdout": result.stdout,
            "stderr": result.stderr,
            "returncode": result.returncode
        })

    except subprocess.TimeoutExpired:
        return jsonify({
            "ok": False,
            "error": "Command timed out after 10s."
        }), 504
    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 500


if __name__ == "__main__":
    import os
    port = int(os.environ.get("PORT", 3000))
    print(f"⚙️ Detected PORT from environment: {port}")
    print(f"⚙️ Launching Flask on 0.0.0.0:{port}")
    # IMPORTANT: no debug, no reloader → stable single PID for nohup
    app.run(host="0.0.0.0", port=port, debug=False, use_reloader=False)
