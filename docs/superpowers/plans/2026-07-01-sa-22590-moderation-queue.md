# SA-22590 Moderation Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the nightly AI moderation queue job so it finds OpenAI sessions that need review, queues moderation work, and avoids re-queueing sessions already moderated for their latest message.

**Architecture:** Keep `tools/ai/moderation-queue.php` as a scheduler only. Move candidate selection and idempotency into testable services under `library/Schoolbox/Admin/OpenAI/ModerationQueue/`, queue taskd HTTP requests for each eligible session, and add a Schoolbox endpoint that reuses `Schoolbox\OpenAi\Agent\InteractionModeration` to perform one session moderation and persist an `ai_moderation_event`.

**Tech Stack:** PHP 8.4, Schoolbox CLI `CliTool\App`, Mapper models for `ai_session`, `ai_session_log`, `ai_moderation_event`, taskd `http-request` messages, PHPUnit.

---

## Current Context

- Jira SA-22590 still says `tools/openai/moderation-queue.php`, but the checkout has moved the script to `tools/ai/moderation-queue.php`.
- `tools/ai/interactionModeration.php` is the manual single-session example. It already demonstrates the required agent flow: fetch `ai_session_log` rows for one target session, build `InteractionModeration`, request moderation, and persist one `ai_moderation_event`.
- `ai_session` has `created_at` only. `last_message_sent_at`, `last_updated_at`, and `last_moderated_at` should be derived from `ai_session_log.created_at` and `ai_moderation_event.created_at` unless product explicitly asks for stored columns.
- The existing `ai_moderation_event` table records completed moderation results, not queued jobs. The implementation should avoid adding a new queue table unless taskd cannot satisfy retry/idempotency requirements.
- Existing placeholder artifact tests still reference `tools/openai/moderation-queue.php`; update them to `tools/ai/moderation-queue.php`.

## Files

- Modify: `library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php`
- Modify: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CommandLineToolTest.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Candidate.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/CandidateFinder.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/TaskMessageFactory.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Moderator.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/CandidateFinderTest.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/TaskMessageFactoryTest.php`
- Create: `library/Schoolbox/Admin/OpenAI/ModerationQueue/Test/ModeratorTest.php`
- Modify: `library/Schoolbox/Admin/OpenAI/Moderation/Controller.php`
- Modify: `library/Schoolbox/Admin/OpenAI/Moderation/Module.php`
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
- [ ] Exclude moderation-agent sessions themselves with `ai_session.feature <> Feature::AI_MODERATION_AGENT->value`.
- [ ] Include sessions with at least one log where no moderation event exists, or where `last_message_sent_at > last_moderated_at`.
- [ ] Order oldest eligible sessions first so nightly work makes progress deterministically.
- [ ] Add PHPUnit coverage for:
  - never moderated session is returned.
  - session moderated after latest log is skipped.
  - session with a newer log after moderation is returned.
  - moderation-agent sessions are skipped.
  - limit is respected.

## Task 3: Add Taskd Message Factory

- [ ] Create `TaskMessageFactory` that builds a taskd `http-request` message pointing to a new internal moderation route with body `sessionId=<uuid>`.
- [ ] Reuse the existing `taskd.httpRequestMessageFactory`, `taskd.messageProducer`, `httpFactory`, and fully-qualified URL pattern used by DialogAgent taskd work.
- [ ] Use the system user/null effector unless product requires the original session user.
- [ ] Add tests asserting the generated request method, route, body, and task type.

## Task 4: Replace Placeholder CLI Loop With Queueing

- [ ] Inject or resolve `CandidateFinder`, `TaskMessageFactory`, and `Producer` in `CommandLineTool`.
- [ ] Preserve existing options and lock behaviour: `-p`, `-n`, `--batch`, `--max-time`.
- [ ] In dry-run mode, log each eligible session that would be queued without sending taskd messages.
- [ ] In perform mode, send one taskd message per candidate.
- [ ] Keep the loop bounded by limit, batch size, max runtime, and lock refresh.
- [ ] Log counts: candidates inspected, queued, skipped by dry-run, and early stop reason.
- [ ] Add tests for dry-run not sending, perform sending, no candidates exiting cleanly, and max limit batching.

## Task 5: Add Moderation Worker Endpoint

- [ ] Add a route in `library/Schoolbox/Admin/OpenAI/Moderation/Module.php`, for example `POST /queue/process`, authorized for taskd/system access only.
- [ ] Add `Controller::postProcessQueuedSession()` that accepts `sessionId`.
- [ ] Delegate actual work to `Moderator` so the controller stays thin and unit-testable.
- [ ] `Moderator` should:
  - Re-check idempotency immediately before processing so duplicate queued messages do not create duplicate moderation events.
  - Fetch logs in chronological order from `Schoolbox\OpenAi\Session\Log\Mapper`.
  - Return a no-op result if the session has no logs or is already moderated for the latest log.
  - Build the moderation config from `ConfigMapper::NAME_AI_MODERATION_AGENT`.
  - Build `InteractionModeration` using `ai.clientFactory` and `Feature::AI_MODERATION_AGENT`.
  - Generate a fresh moderation session UUID.
  - Persist `ai_moderation_event` with `NO_BEHAVIOUR_FLAGGED` when no categories are returned, otherwise `REQUIRES_ATTENTION`.
- [ ] Start from the implementation shape in `tools/ai/interactionModeration.php`, then remove CLI-only output and exits.

## Task 6: Verify Idempotency and API Behaviour

- [ ] Add tests around `Moderator` for:
  - creates one event for a session with logs.
  - maps flagged result to `REQUIRES_ATTENTION`.
  - maps empty category result to `NO_BEHAVIOUR_FLAGGED`.
  - does not create a second event when rerun without newer logs.
  - creates a new event when a newer log exists after the last moderation event.
- [ ] Add a controller or module test if an existing admin module test harness is available; otherwise cover the route’s service integration at `Moderator` and `TaskMessageFactory` level.

## Task 7: Final Verification

- [ ] Run PHP syntax checks on changed PHP files:
  ```bash
  php -l tools/ai/moderation-queue.php
  php -l library/Schoolbox/Admin/OpenAI/ModerationQueue/CommandLineTool.php
  php -l library/Schoolbox/Admin/OpenAI/Moderation/Controller.php
  ```
- [ ] Run focused PHPUnit:
  ```bash
  ./vendor/bin/phpunit library/Schoolbox/Admin/OpenAI/ModerationQueue/Test
  ```
- [ ] Run PHPCS on changed PHP files:
  ```bash
  ./vendor/bin/phpcs library/Schoolbox/Admin/OpenAI/ModerationQueue library/Schoolbox/Admin/OpenAI/Moderation/Controller.php library/Schoolbox/Admin/OpenAI/Moderation/Module.php tools/ai/moderation-queue.php
  ```
- [ ] Run PHPStan on changed PHP files:
  ```bash
  ./vendor/bin/phpstan analyze library/Schoolbox/Admin/OpenAI/ModerationQueue library/Schoolbox/Admin/OpenAI/Moderation/Controller.php library/Schoolbox/Admin/OpenAI/Moderation/Module.php
  ```
- [ ] If Docker is available, repeat final backend checks inside `docker/ssh` before handoff.

## Open Decisions

- Confirm whether taskd already has or will get a purpose-built `ai-interaction-moderation` task. If not, use the existing `http-request` task to call back into Schoolbox as planned above.
- Confirm whether “last_updated_at” has a product meaning beyond latest session log time. No current `ai_session` column stores it.
- Confirm whether eligible sessions should include all `Feature` values except `AI_MODERATION_AGENT`, or only dialog-agent/chat style features.
