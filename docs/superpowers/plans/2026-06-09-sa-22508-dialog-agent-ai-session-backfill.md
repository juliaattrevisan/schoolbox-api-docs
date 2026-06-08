# Dialog Agent AI Session Backfill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add and backfill `dialog_agent_response.ai_session_id` so dialog agent responses can reference the new `ai_session` table.

**Architecture:** This is a database-only slice. Add a nullable `ai_session_id` column and foreign key in one update script, then copy existing `session_id` values into it in the next script. PHP mapper/model changes and `openai_log` deprecation remain out of scope for later PR steps.

**Tech Stack:** MariaDB SQL update scripts under `updates/26.1.alpha1/ai`.

**Spec:** `docs/superpowers/specs/2026-06-09-sa-22508-dialog-agent-ai-session-backfill-design.md`

---

## File Structure

- Create `updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql`
  - Adds the nullable `dialog_agent_response.ai_session_id` column.
  - Adds an index for the new column.
  - Adds the FK to `ai_session(id)`.
- Create `updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql`
  - Copies existing `dialog_agent_response.session_id` values into `ai_session_id`.

Do not modify `sql/initial.sql`; the PR description says it is updated by a separate release process.

## Task 1: Add `dialog_agent_response.ai_session_id`

**Files:**
- Create: `updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql`

- [ ] **Step 1: Create the update script**

Create `updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql` with:

```sql
ALTER TABLE `dialog_agent_response`
    ADD COLUMN `ai_session_id` char(36) DEFAULT NULL AFTER `session_id`,
    ADD KEY `dialog_agent_response_ai_session_id_fk` (`ai_session_id`),
    ADD CONSTRAINT `dialog_agent_response_ai_session_id_fk`
        FOREIGN KEY (`ai_session_id`) REFERENCES `ai_session` (`id`);
```

- [ ] **Step 2: Check SQL script content**

Run:

```bash
sed -n '1,80p' updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql
```

Expected: the file contains exactly the `ALTER TABLE` statement from Step 1.

- [ ] **Step 3: Commit**

```bash
git add updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql
git commit -m "Add dialog agent AI session relation [SA-22508]"
```

## Task 2: Backfill `dialog_agent_response.ai_session_id`

**Files:**
- Create: `updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql`

- [ ] **Step 1: Create the backfill script**

Create `updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql` with:

```sql
UPDATE `dialog_agent_response`
SET `ai_session_id` = `session_id`
WHERE `ai_session_id` IS NULL
  AND `session_id` IS NOT NULL;
```

- [ ] **Step 2: Check SQL script content**

Run:

```bash
sed -n '1,80p' updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql
```

Expected: the file contains exactly the `UPDATE` statement from Step 1.

- [ ] **Step 3: Commit**

```bash
git add updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql
git commit -m "Backfill dialog agent AI session ids [SA-22508]"
```

## Task 3: Verify Migration Ordering And Result

**Files:**
- Verify: `updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql`
- Verify: `updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql`

- [ ] **Step 1: Confirm file ordering**

Run:

```bash
rg --files updates/26.1.alpha1/ai | sort
```

Expected: the scripts appear after the existing AI session scripts, in this order:

```text
updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql
updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql
```

- [ ] **Step 2: Apply the scripts in Docker**

Run the normal update/provision path, or apply the two scripts after the existing AI migration scripts in a Docker database.

For direct verification against a provisioned local database, run:

```bash
mariadb -h 127.0.0.1 -u schoolboxdocker -pabc123 schoolbox < updates/26.1.alpha1/ai/09-add-dialog-agent-response-ai-session-id.sql
mariadb -h 127.0.0.1 -u schoolboxdocker -pabc123 schoolbox < updates/26.1.alpha1/ai/10-fill-dialog-agent-response-ai-session-id.sql
```

Expected: both commands complete without SQL errors.

- [ ] **Step 3: Confirm backfill result**

Run:

```bash
mariadb -h 127.0.0.1 -u schoolboxdocker -pabc123 schoolbox -e "SELECT COUNT(*) AS missing_ai_session_id FROM dialog_agent_response WHERE session_id IS NOT NULL AND ai_session_id IS NULL;"
```

Expected:

```text
missing_ai_session_id
0
```

- [ ] **Step 4: Confirm foreign key metadata**

Run:

```bash
mariadb -h 127.0.0.1 -u schoolboxdocker -pabc123 schoolbox -e "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dialog_agent_response' AND COLUMN_NAME = 'ai_session_id';"
```

Expected:

```text
CONSTRAINT_NAME	TABLE_NAME	COLUMN_NAME	REFERENCED_TABLE_NAME	REFERENCED_COLUMN_NAME
dialog_agent_response_ai_session_id_fk	dialog_agent_response	ai_session_id	ai_session	id
```

- [ ] **Step 5: Final status check**

Run:

```bash
git status --short
```

Expected: no uncommitted files from steps 3 and 4. Pre-existing unrelated local files may still be present and should not be included in these commits.
