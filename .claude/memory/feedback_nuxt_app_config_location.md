---
name: Nuxt app.config.ts location
description: app.config.ts must be inside frontend/app/, not frontend/
type: feedback
---

`app.config.ts` belongs at `frontend/app/app.config.ts`, not `frontend/app.config.ts`.

**Why:** Nuxt looks for app config inside the `app/` source directory. Placing it at the root of `frontend/` causes it to be ignored silently.

**How to apply:** Whenever creating or referencing `app.config.ts` in this project, always use the `frontend/app/` path.
