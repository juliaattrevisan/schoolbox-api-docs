# SA-22590 Moderation Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the nightly AI moderation queue job so it finds OpenAI sessions that need review, processes them in bounded batches, and avoids re-processing sessions already moderated for their latest message.

**Architecture:** Keep `tools/ai/moderation-queue.php` as the scheduled CLI entry point that both discovers and processes eligible sessions. Move candidate selection, feature filtering, and per-session moderation into testable services under `library/Schoolbox/Admin/OpenAI/ModerationQueue/`; the CLI loops through batches until it reaches the requested limit, finds no more eligible sessions, or runs out of its configured max runtime.

**Tech Stack:** PHP 8.4, Schoolbox CLI `CliTool\App`, Mapper models for `ai_session`, `ai_session_log`, `ai_moderation_event`, `Schoolbox\OpenAi\Agent\InteractionModeration`, PHPUnit.

---

## Current Context

- Jira SA-22590 still says `tools/openai/moderation-queue.php`, but the checkout has moved the script to `tools/ai/moderation-queue.php`.
- `tools/ai/interactionModeration.php` is the manual single-session example. It already demonstrates the required agent flow: fetch `ai_session_log` rows for one target session, build `InteractionModeration`, request moderation, and persist one `ai_moderation_event`.
- `ai_session` has `created_at` only. `last_message_sent_at`, `last_updated_at`, and `last_moderated_at` should be derived from `ai_session_log.created_at` and `ai_moderation_event.created_at` unless product explicitly asks for stored columns.
- The existing `ai_moderation_event` table records completed moderation results. The implementation should not add a new queue table for this ticket; reruns stay safe by deriving whether the latest log has already been moderated.
- The CLI should scan a hard-coded allowlist or denylist of AI features. At minimum, it must not scan `Feature::AI_MODERATION_AGENT` sessions, because those are created by the moderation agent itself.
- Existing placeholder artifact tests still reference `tools/openai/moderation-queue.php`; update them to `tools/ai/moderation-queue.php`.

## Files

- Modify: `library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php`
- Modify: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Candidate.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/CandidateFinder.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Moderator.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CandidateFinderTest.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/ModeratorTest.php`
- Modify only if needed for DI wiring: `library/Alaress/Application/Kernel.php`
- Verify existing artifacts: `tools/ai/moderation-queue.php`, `tools/cron/schoolbox`, `tools/logrotate/schoolbox`, `library/Alaress/Lock/CommandLineTool/Inspector.php`

## Task 1: Fix Moved Script Artifact Tests

- [ ] Update `CommandLineToolTest` expectations from `tools/openai/moderation-queue.php` to `tools/ai/moderation-queue.php`.
- [ ] Add/keep assertions for `-n1000`, `--batch=100`, `--max-time=1800`, `/var/log/schoolbox/ai-moderation-queue.log`, executable wrapper, logrotate entry, and lock inspector label.
- [ ] Run:
  ```bash
  ./vendor/bin/phpunit library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php
  ```
- [ ] Commit only the artifact test correction if it is not already fixed.

## Task 2: Add Candidate Selection Service

- [ ] Create `Candidate` as a small immutable value object containing `sessionId`, `lastMessageSentAt`, and `lastModeratedAt`.
- [ ] Create `CandidateFinder` with a method like:
  ```php
  public function find(int $limit, int $offset = 0): array
  ```
- [ ] Implement the query from `ai_session` and `ai_session_log`, deriving:
  - `last_message_sent_at`: `MAX(ai_session_log.created_at)` for the target session.
  - `last_updated_at`: use the same value unless product confirms another source.
  - `last_moderated_at`: latest `ai_moderation_event.created_at` where `target_ai_session_id = ai_session.id`.
- [ ] Add a hard-coded feature filter in `CandidateFinder`. Use either:
  - a denylist containing `Feature::AI_MODERATION_AGENT`, or
  - an allowlist of product features agreed for moderation.
- [ ] Start with the denylist if no narrower product list has been confirmed; it satisfies the known constraint without guessing which user-facing features should be excluded.
- [ ] Include sessions with at least one log where no moderation event exists, or where `last_message_sent_at > last_moderated_at`.
- [ ] Order oldest eligible sessions first so nightly work makes progress deterministically.
- [ ] Add PHPUnit coverage for:
  - never moderated session is returned.
  - session moderated after latest log is skipped.
  - session with a newer log after moderation is returned.
  - moderation-agent sessions are skipped.
  - a non-moderation-agent feature is eligible when its timestamps require moderation.
  - limit is respected.

## Task 3: Add Single-Session Moderator

- [ ] Create `Moderator` with a method like:
  ```php
  public function moderate(string $targetSessionId): ModerationResult
  ```
- [ ] Reuse the implementation shape from `tools/ai/interactionModeration.php`.
- [ ] Fetch logs in chronological order from `Schoolbox\OpenAi\Session\Log\Mapper`.
- [ ] Re-check idempotency immediately before processing so repeated CLI runs do not create duplicate moderation events.
- [ ] Return a no-op result if the session has no logs or is already moderated for the latest log.
- [ ] Build the moderation config from `ConfigMapper::NAME_AI_MODERATION_AGENT`.
- [ ] Build `InteractionModeration` using `ai.clientFactory` and `Feature::AI_MODERATION_AGENT`.
- [ ] Generate a fresh moderation session UUID for the moderation-agent request.
- [ ] Persist `ai_moderation_event` with `NO_BEHAVIOUR_FLAGGED` when no categories are returned, otherwise `REQUIRES_ATTENTION`.
- [ ] Add tests for:
  - creates one event for a session with logs.
  - maps flagged result to `REQUIRES_ATTENTION`.
  - maps empty category result to `NO_BEHAVIOUR_FLAGGED`.
  - does not create a second event when rerun without newer logs.
  - creates a new event when a newer log exists after the last moderation event.
  - returns a no-op result when there are no logs.

## Task 4: Replace Placeholder CLI Loop With Direct Batch Processing

- [ ] Inject or resolve `CandidateFinder` and `Moderator` in `CommandLineTool`.
- [ ] Preserve existing options and lock behaviour: `-p`, `-n`, `--batch`, `--max-time`.
- [ ] In dry-run mode, log each eligible session that would be moderated without calling the moderation agent or writing an event.
- [ ] In perform mode, call `Moderator::moderate()` for each candidate.
- [ ] Keep the loop bounded by limit, batch size, max runtime, and lock refresh.
- [ ] After each batch, check whether `time() > $endTime`; if so, log the runtime stop and exit cleanly so the next nightly run continues from the remaining eligible sessions.
- [ ] Continue to the next batch when the batch completes and runtime remains.
- [ ] Stop when the candidate finder returns no sessions.
- [ ] Log counts: candidates inspected, moderated, skipped by dry-run, no-op idempotency skips, errors, and early stop reason.
- [ ] Decide whether a single session moderation failure should stop the job or be logged and skipped. Prefer logging and continuing unless the failure is systemic, because one bad session should not block all later candidates.
- [ ] Add tests for dry-run not processing, perform processing, no candidates exiting cleanly, max limit batching, and max-time early exit between batches.

## Task 5: Final Verification

- [ ] Run PHP syntax checks on changed PHP files:
  ```bash
  php -l tools/ai/moderation-queue.php
  php -l library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php
  ```
- [ ] Run focused PHPUnit:
  ```bash
  ./vendor/bin/phpunit library/Schoolbox/Admin/OpenAI/ModerationQueue/Test
  ```
- [ ] Run PHPCS on changed PHP files:
  ```bash
  ./vendor/bin/phpcs library/Schoolbox/Admin/OpenAI/ModerationQueue tools/ai/moderation-queue.php
  ```
- [ ] Run PHPStan on changed PHP files:
  ```bash
  ./vendor/bin/phpstan analyze library/Schoolbox/Admin/OpenAI/ModerationQueue
  ```
- [ ] If Docker is available, repeat final backend checks inside `docker/ssh` before handoff.

## Open Decisions

- Confirm whether “last_updated_at” has a product meaning beyond latest session log time. No current `ai_session` column stores it.
- Confirm whether eligible sessions should include all `Feature` values except `AI_MODERATION_AGENT`, or only a smaller allowlist of user-facing features.
