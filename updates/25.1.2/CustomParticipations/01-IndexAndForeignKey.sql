-- Adding index for original_assessment_id in assessment table
CREATE INDEX assessment_original_assessment_id
    ON assessment (original_assessment_id);

-- Adding foreign key constraint for original_assessment_id in assessment table
ALTER TABLE assessment
    ADD CONSTRAINT fk_assessment_original_assessment_id
        FOREIGN KEY (original_assessment_id) REFERENCES assessment (id);

