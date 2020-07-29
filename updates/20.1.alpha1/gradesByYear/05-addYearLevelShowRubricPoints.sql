ALTER TABLE lu_year_level
    ADD COLUMN show_rubric_points TINYINT(1) DEFAULT 1 AFTER display_progression_grade;
UPDATE lu_year_level SET
    show_rubric_points = (SELECT value FROM config WHERE name = 'rubric_show_points');
