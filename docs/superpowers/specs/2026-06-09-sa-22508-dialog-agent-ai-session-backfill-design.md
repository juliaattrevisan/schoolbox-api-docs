# SA-22508 - Dialog agent response AI session backfill

**Ticket:** [SA-22508](https://schoolbox.atlassian.net/browse/SA-22508) - Update code to use `ai_session` and `ai_session_log` tables
**PR:** [#35132](https://github.com/alaress/schoolbox/pull/35132)
**Date:** 2026-06-09

## Scope

This design covers only steps 3 and 4 from the PR description:

1. Add `dialog_agent_response.ai_session_id` linked by foreign key to `ai_session.id`.
2. Fill `dialog_agent_response.ai_session_id`.

This slice intentionally does not change PHP mapper/model code, runtime OpenAI logging code, `openai_log` usage, or `sql/initial.sql`. Those are later PR steps.

## Existing State

The existing `dialog_agent_response.session_id` column points at `openai_log.session_id`. Earlier update scripts in this PR:

- backfill missing `openai_log.session_id` values,
- create `ai_session`,
- fill `ai_session.id` from `openai_log.session_id`.

Because of that ordering, every non-null `dialog_agent_response.session_id` should already have a matching `ai_session.id` before this slice runs.

## Design

Add a new update script after `08-fill-ai-session-log-table.sql`:

`updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql`

The script will alter `dialog_agent_response` by adding:

- `ai_session_id char(36) DEFAULT NULL` after `session_id`,
- an index for the new column,
- a foreign key from `ai_session_id` to `ai_session(id)`.

The column remains nullable because application code still writes and reads `session_id` until step 5 changes the dialog-agent response mapper/model.

Add a second update script:

`updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql`

The script will copy existing values:

```sql
UPDATE dialog_agent_response
SET ai_session_id = session_id
WHERE ai_session_id IS NULL
  AND session_id IS NOT NULL;
```

Adding the foreign key before the fill is intentional. If any existing `session_id` does not correspond to an `ai_session.id`, the migration should fail rather than silently preserving inconsistent data.

## Verification

No automated tests are needed for these steps because they are simple schema/data migrations.

Verify the two SQL scripts by applying them after the existing AI update scripts in the Docker database, or by running the repository's normal update/provision path.

Post-migration checks:

```sql
SELECT COUNT(*)
FROM dialog_agent_response
WHERE session_id IS NOT NULL
  AND ai_session_id IS NULL;
```

Expected result: `0`.

Also verify the foreign key exists and points to `ai_session(id)`.
