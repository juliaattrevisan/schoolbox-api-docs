-- The schema allows grade levels to be INT values: however they should be
-- between 0 to 100. Clean that up here.
UPDATE submission_grade SET lower_bound = 0   WHERE lower_bound < 0;
UPDATE submission_grade SET lower_bound = 100 WHERE lower_bound > 100;
UPDATE submission_grade SET raw_grade   = 0   WHERE raw_grade   < 0;
UPDATE submission_grade SET raw_grade   = 100 WHERE raw_grade   > 100;

-- Allow grade levels to be two-digit decimals
ALTER TABLE submission_grade MODIFY lower_bound DECIMAL(5,2) UNSIGNED NOT NULL;
ALTER TABLE submission_grade MODIFY raw_grade   DECIMAL(5,2) UNSIGNED NOT NULL;
