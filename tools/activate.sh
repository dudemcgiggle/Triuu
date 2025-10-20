#!/usr/bin/env bash
export WP_PATH="${WP_PATH:-./wordpress}"
export PATH="$PWD/tools:$PATH"
echo "[tools/activate.sh] PATH updated. 'wp' now points to ./tools/wp and will auto-heartbeat."
