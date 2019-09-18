-- Add the ability for a SB assessment to be optionally mapped to an external assessment
ALTER TABLE assessment
    ADD COLUMN external_id VARCHAR(32) NULL COMMENT 'External-system specific identifier' AFTER default_open_span;

CREATE INDEX idx_assessment_external_id ON assessment(external_id);
