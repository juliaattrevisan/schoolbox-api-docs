-- Add the ability for a SB quiz to be optionally mapped to an external quiz
ALTER TABLE assess
    ADD COLUMN external_id VARCHAR(32) NULL COMMENT 'External-system specific identifier' AFTER mark_visibility;

CREATE INDEX idx_assess_external_id ON assess (external_id);
