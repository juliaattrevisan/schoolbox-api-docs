-- Store the lower bound and raw grade old values, just in case
ALTER TABLE submission_grade
ADD COLUMN _lower_bound INT NOT NULL AFTER lower_bound,
ADD COLUMN _raw_grade   INT NOT NULL AFTER raw_grade;
UPDATE submission_grade SET _lower_bound = lower_bound, _raw_grade = raw_grade;

-- The schema allows grade levels to be INT values: however they should be
-- between 0 to 100. Clean that up here.
UPDATE submission_grade SET lower_bound = 0   WHERE lower_bound < 0;
UPDATE submission_grade SET lower_bound = 100 WHERE lower_bound > 100;
UPDATE submission_grade SET raw_grade   = 0   WHERE raw_grade   < 0;
UPDATE submission_grade SET raw_grade   = 100 WHERE raw_grade   > 100;

-- Allow grade levels to be two-digit decimals
ALTER TABLE submission_grade MODIFY lower_bound DECIMAL(5,2) UNSIGNED NOT NULL;
ALTER TABLE submission_grade MODIFY raw_grade   DECIMAL(5,2) UNSIGNED NOT NULL;
