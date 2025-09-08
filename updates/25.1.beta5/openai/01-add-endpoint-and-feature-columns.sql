-- Add endpoint column to track API endpoint used
ALTER TABLE openai_log 
ADD COLUMN endpoint VARCHAR(255) NULL AFTER model;

-- Add feature column to categorize AI operations
ALTER TABLE openai_log 
ADD COLUMN feature ENUM('chat', 'response', 'image') NULL AFTER endpoint;