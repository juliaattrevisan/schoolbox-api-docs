CREATE TABLE lti_line_item
(
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lti_assessment_id   INT UNSIGNED NULL,
    lti_assessment_guid UUID NULL,
    label               VARCHAR(255) NULL,
    score_maximum       FLOAT UNSIGNED NULL,
    resource_id         VARCHAR(255) NULL,
    resource_link_id    VARCHAR(255) NULL,
    tag                 VARCHAR(255) NULL,
    start_date          DATETIME NULL,
    end_date            DATETIME NULL,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NOT NULL,
    deleted_at          DATETIME NULL,
    CONSTRAINT lti_line_item_lti_assessment_id_fk
        FOREIGN KEY (lti_assessment_id) REFERENCES lti_assessment (id)
);
