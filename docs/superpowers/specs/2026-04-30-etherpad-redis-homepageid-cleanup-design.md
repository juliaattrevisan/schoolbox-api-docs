# Design: Etherpad Redis Cleanup by Homepage ID

**Date:** 2026-04-30  
**Status:** Drafted from Jira SA-21886

---

## Background

Schoolbox stores Etherpad pad metadata in the local `etherpad_pad` table, including `homepage_id` and `pad_id`. The live Etherpad process stores session and pad state in Redis using Etherpad-specific keys such as `pad:g.*`, `sessionstorage:*`, `ueberDB:keys:*`, and `group2sessions:g.*`.

A Homepage-level cleanup flow is needed when a homepage is being deleted or migrated and its associated collaborative documents must be removed from Etherpad/Redis in a safe, consistent way.

The existing cleanup tooling is limited:

- `tools/etherpad-sync.php` backs up pad HTML from Etherpad into the local DB
- `tools/redis/removeEtherpadSession.php` removes generic Etherpad session keys from Redis
- `www/collabdoc/modifyForm.php` deletes a single pad through the Etherpad API

These tools do not provide a homepage-scoped Redis cleanup path.

---

## Problem

Redis entries are not keyed by `homepage_id`, so there is no direct Redis query that says "delete all Etherpad keys belonging to homepage X".

The only reliable homepage-level linkage is the local `etherpad_pad` metadata table, which tracks which pads were created under a given `homepage_id`.

A homepage cleanup feature must therefore:

- use `homepage_id` to find all relevant local Etherpad pad records
- use `pad_id` to identify the Etherpad pad in the Etherpad service
- delete the pad through Etherpad before removing Redis entries
- optionally clean remaining Redis keys that may remain after a pad delete

---

## Goals

- Add a homepage-scoped Etherpad cleanup flow.
- Ensure all pads attached to a specific `homepage_id` are removed from Etherpad and Redis.
- Keep the homepage-level cleanup safe and reversible in dry-run mode when possible.
- Avoid attempting a Redis-only delete by `homepage_id`.
- Reuse existing local metadata and Etherpad API flows.
- Provide a CLI entry point for operators and a reproducible cleanup path.

## Non-goals

- Adding a generic homepage-to-Redis key indexing system.
- Changing how Etherpad stores data in Redis.
- Deleting unrelated Redis keys such as notifications, cache, or other system data.
- Replacing the existing `collabdoc` edit/pad lifecycle.

---

## Architecture

Introduce a new CLI tool and helper service for homepage-scoped Etherpad cleanup.

### Files

| File | Change |
|---|---|
| `tools/etherpad-homepage-cleanup.php` | New CLI entry point for homepage-scoped Etherpad cleanup |
| `library/Etherpad/HomepageCleanup.php` | New helper for homepage-scoped pad lookup, API delete, and optional Redis key cleanup |
| `library/Etherpad/PadMapper.php` | No change; existing metadata lookup remains the source of truth |
| `tools/redis/removeEtherpadSession.php` | Optional extension to expose a Redis cleanup helper if direct key deletion is needed |

No database schema changes are required.

### Behavior

The new cleanup should behave like this:

1. Accept `homepage_id` as input.
2. Query `etherpad_pad` for all active pads where `homepage_id = :homepageId` and `deleted_at IS NULL`.
3. For each pad:
   - call `EtherpadLite\Client::deletePad($padId)`
   - if the pad delete succeeds, optionally remove the local `etherpad_pad` record or set `deleted_at`
   - if the pad delete fails because the pad is missing, continue to Redis cleanup and log the condition
4. Optionally clean remaining Etherpad Redis keys belonging to the pad’s group or author/session state.
5. Report results and failures.

### Key design observation

Because Redis does not contain `homepage_id`, the local pad metadata is the only stable homepage-level source of truth. This means the cleanup is inherently a two-step flow:

- local metadata → `pad_id`
- `pad_id` → Etherpad/Redis cleanup

---

## Intended Behavior

### Homepage-level cleanup flow

- A user runs the cleanup for `homepage_id = 123`.
- The tool loads pad metadata from `etherpad_pad`:
  - `id`
  - `pad_id`
  - `name`
  - `environment`
- The tool calls the Etherpad API for each `pad_id`.
- If the pad exists, the Etherpad server deletes it and its Redis-backed state.
- If the pad does not exist, the tool logs a warning but continues.
- After the API delete, the tool can optionally also remove any leftover Redis keys matching known Etherpad patterns.

### Redis cleanup scope

The cleanup may include one or both of these actions:

- delete the pad through Etherpad API, which is the primary and safest path
- delete Redis keys for remaining Etherpad state if a pad delete does not fully remove them

Known Etherpad Redis patterns include:

- `pad:g.*`
- `sessionstorage:*`
- `ueberDB:keys:*`
- `group2sessions:g.*`
- `author2sessions:*`
- `token2author:`
- `pad2readonly:g.*`
- `readonly2pad:r.*`
- `group:g.*`
- `session:s.*`

The exact key cleanup may be restricted to what can be safely derived from the pad’s `pad_id`.

### Safety and logging

The tool should log:

- the homepage ID being cleaned
- how many pad records were found
- for each pad, whether the deletion succeeded, was skipped, or failed
- any missing pads or unexpected Etherpad errors
- whether Redis key cleanup was also run

It should avoid blanket Redis deletes unless the key patterns are well understood.

---

## Implementation options

### Option A: CLI tool + helper service

- `tools/etherpad-homepage-cleanup.php` is the operator-facing entry point.
- `library/Etherpad/HomepageCleanup.php` contains the logic and can be reused elsewhere.
- This is the cleanest separation of responsibilities.

### Option B: Extend existing Redis cleanup tool

- Add a homepage-mode to `tools/redis/removeEtherpadSession.php`.
- The tool would still need to resolve `homepage_id` via `etherpad_pad`.
- This is lower-impact but mixes two concerns in one script.

### Recommended approach

Use Option A. The new helper can reuse the existing Redis cleanup logic as a fallback, while keeping homepage-scoped cleanup conceptually separate.

---

## Open questions

- Should this tool automatically soft-delete or remove `etherpad_pad` metadata after successful cleanup?
- Should it support a dry-run mode that reports pads and keys without deleting them?
- Should it clean all Etherpad Redis keys for the pad’s group, or only the pad itself?
- Should it run only in the local environment, or be safe for cloud/production instances where Etherpad/Redis is remote?

---

## Example usage

```bash
php tools/etherpad-homepage-cleanup.php --homepage-id=123
```

Possible extensions:

- `--dry-run`
- `--delete-redis-keys`
- `--soft-delete-meta`
- `--verbose`
