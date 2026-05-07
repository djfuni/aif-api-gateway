# MiMo routing fix notes

This package fixes requests such as `mimov2.5pro`, `mimo-v2.5-pro`, `mimo::mimov2.5pro`, and `xiaomi::mimov2.5pro` so they are routed to Xiaomi MiMo instead of falling back to GLM/NVIDIA presets.

Required environment variables for Xiaomi MiMo:

```env
MIMO_API_BASE=https://token-plan-cn.xiaomimimo.com/v1
MIMO_API_KEY=your_mimo_key
MIMO_DEFAULT_MODEL=mimo-v2.5-pro
```

The root OpenAI-compatible gateway also accepts `XIAOMI_MIMO_API_KEY` and `MIMO_TOKEN_PLAN_API_KEY`.

Security cleanup in this package:

- Removed `.git`, ACME challenge, cache, log, backup, and one-off delivery-note files.
- Removed real `.env` from `aifchat/server`.
- Replaced private keys, SMTP, DB password, payment key, admin hash, JWT secret, and runtime user/API-key JSON data with safe templates.
