# Add a column for documenting exactly which other quiz's question set has been
# (or is being) copied into this quiz.
ALTER TABLE assess
    ADD learnosity_source_assess_id INT NULL AFTER learnosity_job_status;

ALTER TABLE assess
    ADD CONSTRAINT assess_learnosity_source_assess_id_fk
        FOREIGN KEY (learnosity_source_assess_id) REFERENCES assess (assess_id);
