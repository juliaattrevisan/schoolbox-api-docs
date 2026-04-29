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

- Extend `\Schoolbox\Admin\DataRetention\Strategy\Cms` to support homepage-scoped Etherpad cleanup.
- Ensure all pads attached to a specific `homepage_id` are removed from Etherpad and Redis.
- Keep the homepage-level cleanup safe and reversible in dry-run mode when possible.
- Avoid attempting a Redis-only delete by `homepage_id`.
- Reuse existing local metadata and Etherpad API flows.
- Expose cleanup via the existing DataRetention CLI framework rather than a separate operator-only tool.

## Non-goals

- Adding a generic homepage-to-Redis key indexing system.
- Changing how Etherpad stores data in Redis.
- Deleting unrelated Redis keys such as notifications, cache, or other system data.
- Replacing the existing `collabdoc` edit/pad lifecycle.

---

## Architecture

Extend the existing `\Schoolbox\Admin\DataRetention\Strategy\Cms` strategy to support homepage-scoped Etherpad cleanup and add a dedicated operator CLI wrapper.

### Files

| File | Change |
|---|---|
| `tools/etherpad-homepage-cleanup.php` | New operator-facing cleanup tool for homepage-scoped Etherpad removal |
| `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` | Extend the CMS retention strategy to support homepage-scoped Etherpad pad cleanup and optional Redis key cleanup |
| `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` | Add tests for homepage-scoped cleanup behavior and delete ordering |
| `library/Schoolbox/Admin/DataRetention/CommandLineTool.php` | Register the CMS strategy and expose homepage-scoped cleanup through the DataRetention CLI flow |
| `library/Schoolbox/Admin/DataRetention/ValidStrats.php` | Ensure `cms` is available as a valid strategy name |
| `library/Etherpad/PadMapper.php` | No change; existing metadata lookup remains the source of truth |
| `tools/redis/removeEtherpadSession.php` | Optional extension to expose a Redis cleanup helper if direct key deletion is needed |

No database schema changes are required.

### Behavior

The new cleanup should behave like this:

1. Accept `homepage_id` as input through a dedicated operator tool, and optionally support the existing DataRetention strategy interface.
2. Query `etherpad_pad` for all active pads where `homepage_id = :homepageId` and `deleted_at IS NULL`.
3. For each pad:
   - call `EtherpadLite\Client::deletePad($padId)`
   - if the pad delete succeeds, optionally remove the local `etherpad_pad` record or set `deleted_at`
   - if the pad delete fails because the pad is missing, continue to optional Redis cleanup and log the condition
4. Optionally clean remaining Etherpad Redis keys belonging to the pad’s group or author/session state.
5. Report results and failures through the dedicated tool and DataRetention strategy logging.

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

### Option A: Extend `Cms` strategy with a dedicated wrapper tool

- Add homepage-scoped cleanup support to `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`.
- Add `tools/etherpad-homepage-cleanup.php` as an operator-facing wrapper around the CMS strategy.
- This keeps cleanup behavior aligned with existing retention framework while giving operators a simple homepage-level command.

### Option B: Add a dedicated DataRetention command option

- Add a `--homepage-id` or `--cms-homepage-id` option to the existing DataRetention CLI tool.
- The CMS strategy handles the cleanup when this option is present.
- This keeps the logic inside the DataRetention framework, without introducing a completely separate CLI script.

### Recommended approach

Use Option A plus Option B: implement the new `tools/etherpad-homepage-cleanup.php` wrapper and also expose the homepage cleanup through the DataRetention CLI via a `--homepage-id` option. The operator-facing wrapper can invoke the same strategy logic and provide the familiar `--dry-run`, `--delete-redis-keys`, `--soft-delete-meta`, and `--verbose` flags.

---

## Open questions

- Should this tool automatically soft-delete or remove `etherpad_pad` metadata after successful cleanup?
- Should it support a dry-run mode that reports pads and keys without deleting them?
- Should it clean all Etherpad Redis keys for the pad’s group, or only the pad itself?
- Should it run only in the local environment, or be safe for cloud/production instances where Etherpad/Redis is remote?

---

## Example usage

Operator-facing usage for the new tool:

```bash
php tools/etherpad-homepage-cleanup.php --homepage-id=123
```

The tool should support the following flags:

- `--dry-run`
- `--delete-redis-keys`
- `--soft-delete-meta`
- `--verbose`

If the same behavior is exposed through the existing DataRetention CLI, the equivalent command should be:

```bash
php tools/dataRetention/process.php --strats=cms --homepage-id=123 [--dry-run] [--delete-redis-keys] [--soft-delete-meta] [--verbose]
```
