-- Add a denormalized max_value column to rubric_indicator_usage,
-- so that it's not recalculated every time it's queried
ALTER TABLE rubric_indicator_usage
ADD COLUMN max_value DECIMAL(14, 11) UNSIGNED NULL
AFTER submission_box_id;
