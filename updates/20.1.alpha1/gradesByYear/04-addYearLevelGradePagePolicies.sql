ALTER TABLE lu_year_level
    ADD COLUMN display_assessment_grade TINYINT(1) DEFAULT 1 AFTER display_grade_page,
    ADD COLUMN display_rubric_grade TINYINT(1) DEFAULT 1 AFTER display_assessment_grade,
    ADD COLUMN display_capability_grade TINYINT(1) DEFAULT 1 AFTER display_rubric_grade,
    ADD COLUMN display_progression_grade TINYINT(1) DEFAULT 1 AFTER display_capability_grade;
UPDATE lu_year_level SET
    display_assessment_grade = (SELECT value FROM config WHERE name = 'display_grade_graph'),
    display_rubric_grade = (SELECT value FROM config WHERE name = 'enable_grades_criteria'),
    display_capability_grade = (SELECT value FROM config WHERE name = 'grade_capability_display'),
    display_progression_grade = (SELECT value FROM config WHERE name = 'grade_progression_display');
