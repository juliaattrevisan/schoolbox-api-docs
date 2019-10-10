-- Add the ability for a SB assessment to be optionally mapped to an external assessment
ALTER TABLE submission_box
    ADD COLUMN external_id VARCHAR(32) NULL COMMENT 'External-system specific identifier';

CREATE INDEX idx_submission_box_external_id ON submission_box(external_id);
