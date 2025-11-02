import os, requests

SAFE_AGENT_URL = os.getenv("SAFE_AGENT_URL", "https://9a4a1c518c90.ngrok.app")

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
