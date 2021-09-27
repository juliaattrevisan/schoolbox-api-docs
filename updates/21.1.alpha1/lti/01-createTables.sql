CREATE TABLE lti_assessment
(
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assessment_id      INT UNSIGNED NOT NULL,
    lti_assessment_url VARCHAR(2048)    NULL,
    CONSTRAINT lti_assessment_assessment_id_fk
        FOREIGN KEY (assessment_id) REFERENCES assessment (id)
);
CREATE TABLE lti_assessment_score
(
    id                INT UNSIGNED                                                            NOT NULL AUTO_INCREMENT PRIMARY KEY,
    lti_assessment_id INT UNSIGNED                                                            NOT NULL,
    student_id        INT UNSIGNED                                                            NOT NULL,
    marking_user_id   INT UNSIGNED                                                            NULL,
    score_given       FLOAT                                                                   NULL,
    score_maximum     FLOAT                                                                   NULL,
    timestamp         DATETIME                                                                NOT NULL,
    comment           TEXT                                                                    NULL,
    activity_progress ENUM ('Initialized', 'Started', 'InProgress', 'Submitted', 'Completed') NOT NULL,
    grading_progress  ENUM ('NotReady', 'Failed', 'Pending', 'PendingManual', 'FullyGraded')  NOT NULL,
    is_response       BOOL                                                                    NOT NULL DEFAULT FALSE,
    is_feedback       BOOL                                                                    NOT NULL DEFAULT FALSE,
    CONSTRAINT lti_assessment_score_lti_assessment_id_fk
        FOREIGN KEY (lti_assessment_id) REFERENCES lti_assessment (id),
    CONSTRAINT lti_assessment_score_student_id_fk
        FOREIGN KEY (student_id) REFERENCES user (id),
    CONSTRAINT lti_assessment_score_marking_user_id_fk
        FOREIGN KEY (marking_user_id) REFERENCES user (id)
);
