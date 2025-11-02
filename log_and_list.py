from datetime import datetime
import os, requests, sys, json

SAFE_AGENT_URL = os.getenv("SAFE_AGENT_URL", "http://127.0.0.1:3000")

def post(path, payload):
    r = requests.post(f"{SAFE_AGENT_URL}{path}", json=payload, timeout=20)
    r.raise_for_status()
    return r.json()

def write(rel_path, content):
    return post("/write", {"path": rel_path, "content": content})

def read(rel_path):
    return post("/read", {"path": rel_path})

def run(cmd):
    return post("/run", {"cmd": cmd})

def append_log_entry():
    timestamp = datetime.now().isoformat(timespec="seconds")
    entry = f"{timestamp} - Script executed\n"
    try:
        existing = read("workspace/demo/log.txt")["content"]
    except Exception:
        existing = ""
    write("workspace/demo/log.txt", existing + entry)
    print("Appended log entry to workspace/demo/log.txt")

def main():
    append_log_entry()
    print(run("dir"))
    print("DONE: appended log entry and listed workspace directory")

if __name__ == "__main__":
    main()
