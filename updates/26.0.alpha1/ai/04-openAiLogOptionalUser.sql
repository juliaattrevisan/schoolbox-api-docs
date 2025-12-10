-- AI requests can be triggered by the system
ALTER TABLE openai_log MODIFY COLUMN `user_id` INT unsigned NULL;
