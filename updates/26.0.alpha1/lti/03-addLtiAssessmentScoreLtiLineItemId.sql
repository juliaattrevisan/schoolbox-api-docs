ALTER TABLE lti_assessment_score
    ADD lti_line_item_id INT UNSIGNED NULL AFTER lti_assessment_id,
    ADD CONSTRAINT lti_assessment_score_lti_line_item_id_fk
        FOREIGN KEY (lti_line_item_id) REFERENCES lti_line_item (id);
