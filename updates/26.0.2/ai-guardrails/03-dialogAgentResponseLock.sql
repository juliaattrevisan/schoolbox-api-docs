-- A dialog agent response becomes "Locked" when an AI guardrail flags a message the student sent

ALTER TABLE `dialog_agent_response`
    ADD `locked_at` DATETIME NULL AFTER `assistance_requested_at`;
