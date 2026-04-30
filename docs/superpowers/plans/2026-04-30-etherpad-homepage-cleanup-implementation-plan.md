# Etherpad Homepage Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the CMS DataRetention strategy and add a dedicated operator tool so Schoolbox can clean up Etherpad pads and Redis state by `homepage_id` safely and consistently.

**Architecture:** Extend `Schoolbox\Admin\DataRetention\Strategy\Cms` to support homepage-scoped Etherpad cleanup, then add `tools/etherpad-homepage-cleanup.php` as an operator-facing wrapper. Keep the logic inside the retention strategy where possible, and expose the same behavior through the DataRetention CLI if useful.

**Tech Stack:** PHP 8.4, PHPUnit, MariaDB 11.8 SQL via `IConnector`, PSR-3 logging, Symfony Lock, EtherpadLite API.

---

## File Map

| Action | Path |
|---|---|
| Modify | `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php` |
| Create | `tools/etherpad-homepage-cleanup.php` |
| Create | `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/ValidStrats.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php` |
| Modify | `library/Schoolbox/Admin/DataRetention/CommandLineTool.php` |
| Modify | `tools/dataRetention/process.php` (optional) |

---

## Task 1: Extend CMS DataRetention for homepage-scoped Etherpad cleanup

**Files:**
- `library/Schoolbox/Admin/DataRetention/Strategy/Cms.php`
- `library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`
- `library/Schoolbox/Admin/DataRetention/ValidStrats.php`
- `library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`
- `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`

- [x] **Step 1: Ensure `cms` is a valid retention strategy**
  - Add `public const string CMS = 'cms';` to `ValidStrats` if not already present.
  - Add `self::CMS` to `STRATS_WHITELIST`.
  - Add focused tests in `ValidStratsTest.php` covering `cms` acceptance and enablement.

- [x] **Step 2: Add homepage-scoped cleanup entry points to `Cms.php`**
  - Add a protected method to load all pads for a given `homepage_id` from `etherpad_pad`.
  - Add a method to delete a pad via `EtherpadLite\Client::deletePad($padId)`.
  - Add optional support for `--dry-run`, `--delete-redis-keys`, `--soft-delete-meta`, and `--verbose` via flags passed down from the CLI.

- [x] **Step 3: Implement pad cleanup logic**
  - For a homepage cleanup run, query active `etherpad_pad` records where `homepage_id = :homepageId` and `deleted_at IS NULL`.
  - For each pad, attempt an Etherpad API delete.
  - If the pad delete succeeds, optionally soft-delete the local metadata row or remove it depending on the requested behavior.
  - If the pad is missing in Etherpad, continue to optional Redis cleanup and log a warning.

- [x] **Step 4: Add optional Redis cleanup support**
  - Add a helper to delete any remaining Etherpad-related Redis keys when `--delete-redis-keys` is enabled.
  - Restrict Redis key deletion to known Etherpad patterns, and only if they can be safely derived from the pad’s `pad_id` or returned metadata.
  - Ensure this is optional and guarded behind an explicit CLI flag.

- [x] **Step 5: Add focused strategy tests**
  - Create `CmsStrategyTest.php` with a lightweight test subclass of `Cms`.
  - Cover homepage pad lookup, API delete attempts, missing-pad logging, and dry-run behavior.
  - Cover `soft-delete-meta` semantics and conditional Redis cleanup invocation.

- [x] **Step 6: Run strategy and unit tests**
  - Run `./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/CmsStrategyTest.php`
  - Run `./vendor/bin/phpunit library/Schoolbox/Admin/DataRetention/Test/ValidStratsTest.php`

---

## Task 2: Add dedicated operator wrapper tool `tools/etherpad-homepage-cleanup.php`

**Files:**
- `tools/etherpad-homepage-cleanup.php`
- `library/Schoolbox/Admin/DataRetention/CommandLineTool.php`
- `tools/dataRetention/process.php` (optional)

- [ ] **Step 1: Create the operator-facing tool**
  - Add a new script at `tools/etherpad-homepage-cleanup.php`.
  - Accept CLI options:
    - `--homepage-id`
    - `--dry-run`
    - `--delete-redis-keys`
    - `--soft-delete-meta`
    - `--verbose`
  - Validate required input and print help if missing.

- [ ] **Step 2: Invoke the CMS strategy from the tool**
  - Use the existing DataRetention strategy or a shared helper to perform the cleanup.
  - Pass the same flag values through to the strategy implementation.
  - Report results and return a non-zero exit code on failure.

- [ ] **Step 3: Optionally expose the same behavior via `tools/dataRetention/process.php`**
  - Add support for a `--homepage-id` or `--cms-homepage-id` option in the DataRetention CLI.
  - When present, invoke the `Cms` strategy in homepage-cleanup mode.
  - Keep the operator-facing wrapper as the most discoverable command.

- [ ] **Step 4: Add tool-level tests or manual QA steps**
  - If the tool is testable in PHP, add a small CLI test that exercises flag parsing and strategy invocation.
  - Otherwise document manual verification steps in the code or plan.

- [ ] **Step 5: Run the tool and confirm behavior**
  - Manual example command:
    ```bash
    php tools/etherpad-homepage-cleanup.php --homepage-id=123 --dry-run --verbose
    ```
  - Confirm that the tool finds the expected pads and logs what it would delete.

---

## Task 3: Operational validation and documentation

- [ ] Document the new cleanup command and flags in `docs/superpowers/specs/2026-04-30-etherpad-redis-homepageid-cleanup-design.md` or another appropriate location.
- [ ] Confirm the tool is safe for cloud and local environments, especially with remote Etherpad/Redis deployments.
- [ ] Add a short note to the maintenance docs or README if operators need to know about homepage-level Etherpad cleanup.

---

## Example usage

```bash
php tools/etherpad-homepage-cleanup.php --homepage-id=123 --dry-run --verbose
```

Optional CLI flags:

- `--dry-run`
- `--delete-redis-keys`
- `--soft-delete-meta`
- `--verbose`

If the cleanup is also exposed through the DataRetention CLI:

```bash
php tools/dataRetention/process.php --strats=cms --homepage-id=123 [--dry-run] [--delete-redis-keys] [--soft-delete-meta] [--verbose]
```
