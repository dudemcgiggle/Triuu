# DB Dump Policy

This repository intentionally stores WordPress database exports for reproducible environments.
- Dumps are tracked via **Git LFS**.
- No production secrets should appear; sensitive options are sanitized.
- If this repo is public, do not commit PII or credentials. Rotate any accidentally exposed keys immediately.
