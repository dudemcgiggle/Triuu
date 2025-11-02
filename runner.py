import os, sys, json, requests

BASE = os.getenv("SAFE_AGENT_URL", "http://127.0.0.1:3000")

def call(path, payload):
    r = requests.post(f"{BASE}{path}", json=payload, timeout=20)
    r.raise_for_status()
    return r.json()

TOOLS = {
    "read":  lambda s: call("/read",  {"path": s["path"]}),
    "write": lambda s: call("/write", {"path": s["path"], "content": s["content"]}),
    "run":   lambda s: call("/run",   {"cmd": s["cmd"]}),
    "final": lambda s: {"ok": True, "message": s.get("message","")}
}

def main():
    if len(sys.argv) < 2:
        print("usage: python runner.py <plan.jsonl>")
        sys.exit(2)
    plan = sys.argv[1]
    with open(plan, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            step = json.loads(line)
            tool = step.get("tool")
            if tool not in TOOLS:
                raise SystemExit(f"Unknown tool: {tool}")
            out = TOOLS[tool](step)
            print(json.dumps({"step": step, "result": out}, ensure_ascii=False))
            if tool == "final":
                return

if __name__ == "__main__":
    main()
