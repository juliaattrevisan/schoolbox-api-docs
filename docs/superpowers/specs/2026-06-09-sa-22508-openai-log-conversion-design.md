# SA-22508 — Convert code from `openai_log` to `ai_session` / `ai_session_log`

**Ticket:** [SA-22508](https://schoolbox.atlassian.net/browse/SA-22508) — Update code to use `ai_session` and `ai_session_log` tables
**PR:** [#35132](https://github.com/alaress/schoolbox/pull/35132)
**Date:** 2026-06-09

## Scope

This design covers steps 5, 6 and 7 of the PR — the PHP conversion and the table
deprecations. Steps 1–4 (the new mapper/model classes and the schema/data
migrations) are already merged on the branch.

In scope:

1. **Step 5** — point `dialog_agent_response` PHP at the new `ai_session_id` column.
2. **Step 6** — convert every consumer of `Schoolbox\Editor\OpenAI\Log\Mapper` /
   `Model` to the new `Schoolbox\OpenAi\Session\` classes (the runtime write path
   and the admin usage read path).
3. **Step 7** — deprecate the `openai_log` table and the
   `dialog_agent_response.session_id` column by underscore-prefix rename.

Out of scope (explicitly deferred): a PHP mapper-level `joins()` association from
`dialog_agent_response` to `ai_session` (the DB foreign key already exists; no
caller needs the session *object* yet); reworking `returnCode` into a `has_error`
flag; reconciling `Helper::FEATURE_*` constants with the `Feature` enum.

## Background facts that drive the design

- The `aiSessionId` is a UUID minted **in memory** — by the frontend or by the
  controller — *before and independently of* any `ai_session` row. It is not a key
  returned by the database after an insert, so possessing an `aiSessionId` tells
  you nothing about whether the row exists yet. Two ways an id reaches a write:
  - **Controller-minted, fresh:** e.g. `DialogAgent\Controller:324` /
    `Editor\OpenAI\Controller:95` do `Uuid::uuid4()` then immediately log against
    it — at that first write the id is valid but **no row exists**, so this write
    must create the session.
  - **Frontend-supplied:** e.g. `Editor\OpenAI\Controller:164` /
    `DialogAgent\Controller:715` read `aiSessionId` from the POST body (the
    frontend owns it across a conversation). The backend's first sight of the id is
    when the request arrives, and it cannot tell from the id alone whether this is
    a new conversation (no row) or a resumed one (row exists).
  The same id is **reused across multiple HTTP requests** (resumed chats,
  multi-turn dialog agent, image follow-ups), each writing one or more log rows
  under it. So a write can never assume the session row does or does not already
  exist; the first write under a given id is the creation point. `INSERT IGNORE` /
  find-or-create resolves "create vs reuse" atomically in one step. (This is also
  why Option B — explicit session creation when the UUID is minted — was rejected:
  it would handle the controller-minted case but frontend-supplied ids would still
  need find-or-create on arrival, leaving two mechanisms instead of one.)
- In the new schema `feature` lives on `ai_session`, and `ai_session_log` rows FK
  to `ai_session.id`, so the session row must exist before its first log row.
- The backfill (`07-fill-ai-session-table.sql`) collapses `min(feature)` per
  session, i.e. a session is effectively one feature.
- The admin session list genuinely consumes `logFormat` and `returnCode` per
  session (`ai-usage-log.vue` shows an "Error" column and gates the "view log"
  link on `logFormat !== null`), even though those values now live on the logs.

## Write path (step 6, runtime)

### Decision: eager find-or-create in the orchestration layer (no "writer" class)

We considered (a) a dedicated `Session\Log\Writer` wrapping both mappers and (b) a
`Session\Log\Mapper::saveWithSession()` that reaches the session mapper at save
time with `feature` riding transiently on the log model. Both rejected. The new
`Session\Log\Model` deliberately has **no** `feature` (feature moved to the
session), and `saveLog($log)` is called from six internal spots with only the log,
so threading `feature` to a save-time coordinator would force a transient,
write-path-only property back onto the otherwise-pure log model.

Instead, coordinate at the point the log is **built** — where `feature`,
`aiSessionId` and `user` are all in hand — which is also this codebase's dominant
convention (orchestrators coordinate mappers, e.g. controllers injecting several
mappers). The log model stays pure and the log mapper stays a plain mapper.

### Components

- **`Session\Mapper::findOrCreate(string $id, ?string $feature, ?User $user): Model`**
  — find the session by its UUID `id`; if absent, `INSERT IGNORE` it with `feature`
  and `created_by`. First writer wins on `feature` / `created_by` / `created_at`,
  which is correct for a reused session id. Mirrors the Rubric `findOrCreate*`
  methods and the direct `INSERT IGNORE` precedents (`UserMapper`,
  `Pastoral\Tag\Mapper`, `IB\Group\Subject\Language\Mapper`).
- **`OpenAi\Client`** gains an injected `Session\Mapper $sessionMapper` (F2a). Its
  `createLog()` calls `findOrCreate($aiSessionId, $feature, $user)` then
  `$log->setAiSessionId($aiSessionId)`. `feature` is consumed to stamp the session
  and never touches the log model. `saveLog()` stays a plain `logMapper->save()`.
- **`Editor\OpenAI\Helper::request`** does the same eager `findOrCreate(...)` before
  building/saving its inline log (its second, legacy write path).

So `findOrCreate` has exactly two call sites — `Client::createLog` and
`Helper::request` — both of which already hold `feature`/`aiSessionId`/`user`.

Behavioural nuance: `createLog` previously did no DB writes; it now writes the
`ai_session` row as a side effect. In practice every `createLog` is immediately
followed by a request that saves a log, so the session always precedes its logs
(the FK ordering is guaranteed), and an orphan session is only theoretically
possible if a caller builds a log but never sends — which none of the nine agents
do.

### Call sites / DI (F2a injection)

- `OpenAi\Client`: constructor takes `Session\Log\Mapper $logMapper` **and**
  `Session\Mapper $sessionMapper`; `createLog()` does the find-or-create.
- `OpenAi\ClientFactory`: gains a `Session\Mapper` constructor param, passes it into
  each `new Client(...)`.
- `Editor\OpenAI\Helper`: gains an injected `Session\Mapper`.
- `Learning\DialogAgent\Module` / `Controller`, `Learning\Assessment\Module`, and
  `Alaress\Application\Kernel` (the `getLazy` wiring) swap
  `Editor\OpenAI\Log\Mapper` for `Session\Log\Mapper` and wire the new
  `Session\Mapper` where needed.

## Read path — admin usage (step 6)

### Session list

Replace `getGroupedLogs` / `countGroupedLogs` (which `GROUP BY session_id` over
`openai_log`) with **`Session\Mapper::getFilteredSessions(...)` /
`countFilteredSessions(...)`** querying the real `ai_session` table:

- `feature` filter → `ai_session.feature`
- `user` filter → `ai_session.created_by`
- date range → `ai_session.created_at`
- `keyword` (prompt/response) → `EXISTS` subquery against `ai_session_log`

**Deriving `logFormat` / `returnCode` (decision D1):** derive them in the list SQL
from each session's *first* log (earliest by `created_at, id`) via a correlated
join:

```sql
LEFT JOIN ai_session_log firstLog ON firstLog.id = (
    SELECT id FROM ai_session_log
    WHERE ai_session_id = s.id ORDER BY created_at ASC, id ASC LIMIT 1
)
```

`firstLog.log_format` / `firstLog.return_code` are hydrated as transient
`derivedLogFormat` / `derivedReturnCode` on `Session\Model` (in the custom select
hydration, the same way `user` is attached). `Session\Translator\ModelToArray`
reads those instead of session columns. One query, no N+1, and **no frontend
changes** — the `AiSession` payload contract is preserved.

Known simplification (Zac-approved): if a session's first call returned 200 but a
later one errored, the list will not flag "Error" for that session. The old
`GROUP BY` was already non-deterministic about which row it surfaced.

### Session detail

`getLog` becomes `Session\Log\Mapper::findByColumn('ai_session_id', $id, [order
created_at ASC])` translated by the per-log `ModelToArray`. Remove the `feature`
field from that translator and from the `AiSessionLog` TypeScript type — Zac
confirmed it is unused in the frontend.

## Step 5 — `dialog_agent_response`

Decision **5a (minimal repoint):** in
`Learning\Assessment\DataModel\DialogAgent\Response\Mapper`, rebind the column from
`session_id` to `ai_session_id`. Keep the model property and the
`getSessionId()` / `setSessionId()` accessors, so the domain `Model`,
`Transformer`, `NextRoutes` and the DialogAgent `Controller` are untouched. The
PHP join association is deferred (see Scope).

## Step 7 — deprecation migrations

Two new scripts after `10-…`, following the existing numbered convention:

- `11-deprecate-openai-log-table.sql` → `RENAME TABLE openai_log TO _openai_log;`
- `12-deprecate-dialog-agent-response-session-id.sql` → rename the now-unused
  column to `_session_id`.

These run at deploy after the PHP in this PR has stopped touching both, so the
ordering is safe. `sql/initial.sql` is **not** updated here (separate per-release
process).

## Testing

- `Session\Mapper::findOrCreate` — creates on first call, returns existing on
  repeat (no duplicate, original `feature`/`created_by` preserved).
- `Client::createLog` — calls `findOrCreate` (session row created) and stamps
  `aiSessionId` on the log; a subsequent `saveLog` persists the log under the
  existing session; a second `createLog`+save under the same id reuses the session.
- `Session\Mapper::getFilteredSessions` / `countFilteredSessions` — each filter
  (feature, user, date range, keyword-via-`EXISTS`), pagination, and the derived
  `logFormat` / `returnCode` from the first log.
- Dialog-agent response round-trip persists/reads through `ai_session_id`.
- Adjust the admin Usage controller tests for the new mapper methods.

## Verification

- Run the AI/mapper integration tests in the Docker DB.
- Exercise an AI feature end to end (e.g. dialog agent) and confirm an
  `ai_session` row plus its `ai_session_log` rows are written, with nothing new in
  `openai_log`.
- Load the admin AI usage log: the session list, the "Error" column, and the
  per-session "view log" detail all still render.
- After migrations: `openai_log` is renamed to `_openai_log` and
  `dialog_agent_response.session_id` to `_session_id`.
