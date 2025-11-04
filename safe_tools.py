import os, requests

SAFE_AGENT_URL = os.getenv("SAFE_AGENT_URL", "http://127.0.0.1:3000")

def read(path):
    r = requests.post(f"{SAFE_AGENT_URL}/read", json={"path": path}, timeout=20)
    r.raise_for_status()
    return r.json()

def write(path, content):
    r = requests.post(f"{SAFE_AGENT_URL}/write", json={"path": path, "content": content}, timeout=20)
    r.raise_for_status()
    return r.json()

def run(cmd):
    r = requests.post(f"{SAFE_AGENT_URL}/run", json={"cmd": cmd}, timeout=20)
    r.raise_for_status()
    return r.json()
