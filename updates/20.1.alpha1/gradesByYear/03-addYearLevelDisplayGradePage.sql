/** Add display_grade_page column to Year Level list
 */
ALTER TABLE lu_year_level
    ADD COLUMN display_grade_page TINYINT(1) DEFAULT 1 AFTER show_overall_grade;
