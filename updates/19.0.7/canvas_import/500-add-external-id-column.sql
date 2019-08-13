-- Add the ability for a SB assessment to be mapped to an external assessment
ALTER TABLE assessment
ADD COLUMN external_id VARCHAR(32) AFTER default_open_span;

CREATE INDEX idx_assessment_external_id ON assessment(external_id);
