/** Add learning_moments_enabled column to Year Level list
 */
ALTER TABLE lu_year_level
    ADD COLUMN learning_moments_enabled TINYINT(1) DEFAULT 1 AFTER lms_cohort_comparison;
