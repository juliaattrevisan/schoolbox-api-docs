/** Add overall_grade column and show_overall_grade to Year Level list
 */
ALTER TABLE lu_year_level
    ADD COLUMN overall_grade INT DEFAULT NULL AFTER name,
    ADD COLUMN show_overall_grade TINYINT(1) DEFAULT 1 AFTER overall_grade;
UPDATE lu_year_level SET
    show_overall_grade = (SELECT value FROM config WHERE name = 'show_overall_grade');
