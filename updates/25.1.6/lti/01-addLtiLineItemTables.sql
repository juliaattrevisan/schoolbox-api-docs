CREATE TABLE lti_line_item
(
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guid              UUID         NULL,
    lti_assessment_id INT UNSIGNED NULL,
    CONSTRAINT lti_line_item_lti_assessment_id_fk
        FOREIGN KEY (lti_assessment_id) REFERENCES lti_assessment (id)
);
