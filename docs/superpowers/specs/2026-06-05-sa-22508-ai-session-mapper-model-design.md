# SA-22508 — New AI Session mapper/model classes

**Ticket:** [SA-22508](https://schoolbox.atlassian.net/browse/SA-22508) — Update code to use `ai_session` and `ai_session_log` tables
**PR:** [#35132](https://github.com/alaress/schoolbox/pull/35132)
**Date:** 2026-06-05

## Context

SA-22409 (merged into this branch) split the single `openai_log` table into two new
tables and migrated the data:

- **`ai_session`** — `id` char(36) UUID (the old `session_id`), `feature` enum,
  `created_at`, `created_by` (nullable FK to `user`). **`feature` moves up to the
  session level.**
- **`ai_session_log`** — `id` int auto-increment, `ai_session_id` char(36) FK,
  `prompt`, `input_tokens`, `output_tokens`, `input_text`, `output_text`, `model`,
  `endpoint`, `log_format` enum, `response`, `return_code`, `error_message`,
  `created_at`, `created_by` (nullable FK to `user`). **`feature` is gone from the
  log level.**

This sub-task is the **first slice** of SA-22508: add the new mapper and model
classes that map these two tables. Converting the ~35 consumers of
`Schoolbox\Editor\OpenAI\Log\Mapper` / `Model` to the new classes is a **separate,
later phase** and is explicitly out of scope here.

## Decisions (agreed during brainstorming)

1. **Feature representation:** introduce a new `Feature` backed enum (not a plain
   string).
2. **`LogFormat` enum:** reference it in place at `Schoolbox\Editor\OpenAI\Log\LogFormat`
   for now; any relocation is deferred to the cleanup/conversion phase.
3. **Class scope:** pure table mappers + models only. No `getGroupedLogs` /
   `countGroupedLogs` yet — those are redesigned during the conversion phase.
4. **Relationships:** each mapper joins `user` via `created_by` and exposes
   `getUser()/setUser()` (mirroring the old `Model`). Session and Log stay
   independent — Log holds the `ai_session_id` string; no eager Session↔Log join or
   logs collection. Callers fetch a session's logs via the base mapper's
   `findByColumn('ai_session_id', …)`.

All new code lives in the `Schoolbox\OpenAi\Session\` namespace
(`library/Schoolbox/OpenAi/Session/`), since these tables are used by all AI
features, not just the Editor.

## Components

### 1. `Schoolbox\OpenAi\Session\Feature` (new enum)

Backed string enum mirroring the `ai_session.feature` DB enum, modelled on the
existing `Schoolbox\Editor\OpenAI\Log\LogFormat` enum:

```
CHAT                            = 'chat'
LESSON_PLAN                     = 'lessonplan'
IMAGE                           = 'image'
DIFFERENTIATION                 = 'differentiation'
DIALOG_AGENT                    = 'dialogagent'
LEARNING_ACTIVITY_CUSTOMISATION = 'learningactivitycustomisation'
AI_MODERATION_AGENT             = 'aimoderationagent'
```

The existing `Helper::FEATURE_*` string constants are left untouched for now;
reconciling them with this enum is conversion-phase work.

### 2. `Schoolbox\OpenAi\Session\Model`

- `id`: **string** UUID. The column is literally named `id` (char(36)), so the
  mapper maps it to `setId()/getId()`. Defined as a string-typed `id` directly
  (not the int `Id` trait, which would be the wrong type).
- `feature`: backed by `Feature`. `setFeature(string|Feature|null)` normalises to
  the enum (same pattern as `Model::setLogFormat`); `getFeature()` returns the
  string value (to match how the mapper persists it).
- `createdAt`: via `CreatedAtDate` trait.
- `user`: `getUser()/setUser(?User)` populated from the `created_by` join.

### 3. `Schoolbox\OpenAi\Session\Mapper extends Schoolbox\Mapper`

```
hasTable('ai_session');
hasModel(Model::class);

uses('id', TYPE_STRING, COLUMN_IDENTIFIER_UUID);   // auto-generates UUID, cf. Tour\Mapper
uses('feature', TYPE_STRING, COLUMN_ENUM, null, [Feature case values]);
joins('created_by', UserMapper::class, 'id', null, null, 'user', 'user');
uses('created_at', TYPE_DATETIME, COLUMN_CREATED_TIMESTAMP);
```

Plus `getSelectString()` / `getFromString()` / `hydrateSelect()` implementing the
`user` LEFT JOIN, following the structure of the existing
`Editor\OpenAI\Log\Mapper`.

### 4. `Schoolbox\OpenAi\Session\Log\Model`

Carries over all old `Model` log fields **except** `feature` (now on the session),
and renames `sessionId` → `aiSessionId`:

- `id`: int, via `Id` trait (auto-increment).
- `aiSessionId`: string.
- `prompt`, `inputTokens`, `outputTokens`, `inputText`, `outputText`, `model`,
  `endpoint`, `logFormat`, `response`, `returnCode`, `errorMessage` — including the
  existing length-trimming constants and logic for `prompt`, `input_text`,
  `output_text`, `response`.
- `logFormat`: `setLogFormat(string|LogFormat|null)`, importing `LogFormat` from
  `Schoolbox\Editor\OpenAI\Log` (referenced in place).
- `createdAt`: via `CreatedAtDate` trait.
- `user`: `getUser()/setUser(?User)` from the `created_by` join.

### 5. `Schoolbox\OpenAi\Session\Log\Mapper extends Schoolbox\Mapper`

```
hasTable('ai_session_log');
hasModel(Model::class);

uses('id', TYPE_INTEGER, COLUMN_IDENTIFIER, 'id');
uses('ai_session_id', TYPE_STRING);
uses('prompt', TYPE_STRING);
uses('input_tokens', TYPE_INTEGER);
uses('output_tokens', TYPE_INTEGER);
uses('input_text', TYPE_STRING);
uses('output_text', TYPE_STRING);
uses('model', TYPE_STRING);
uses('endpoint', TYPE_STRING);
uses('log_format', TYPE_STRING, COLUMN_ENUM, null, [LogFormat values + null]);
uses('response', TYPE_STRING);
uses('return_code', TYPE_INTEGER);
uses('error_message', TYPE_STRING);
joins('created_by', UserMapper::class, 'id', null, null, 'user', 'user');
uses('created_at', TYPE_DATETIME, COLUMN_CREATED_TIMESTAMP);
```

Plus the `user` LEFT JOIN select/from/hydrate methods, as in the Session mapper.

## Deliberately deferred to the conversion phase

- `getGroupedLogs` / `countGroupedLogs`, redesigned as session-listing queries.
- The `MISSING_SESSIONID` constant from the old `Model`.
- Reconciling `Helper::FEATURE_*` constants with the new `Feature` enum.
- The two frontend `ModelToArray` translators.
- Zac's notes: derive `logFormat` / `returnCode` for a session from its logs, and
  drop `feature` from translated logs and the `AiSessionLog` frontend type.
- Deprecating the `openai_log` table (renaming with an underscore prefix) once it is
  no longer used.

## Testing

Mapper integration tests covering, for both tables:

- persist + hydrate round-trip,
- UUID auto-generation on `ai_session`,
- the `user` join via `created_by`,
- enum handling (`feature` on session, `log_format` on log),
- length-trimming behaviour on the log model's text fields.

The exact harness/pattern will be confirmed against the existing AI/mapper tests
before writing, during the implementation-plan phase.
