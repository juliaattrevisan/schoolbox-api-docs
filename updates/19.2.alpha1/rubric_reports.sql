UPDATE config SET title = 'Display Rubric Descriptors on Grades Page', description='If on, the rubric descriptors from all marked assessments will be shown on the students grades page' WHERE name = 'enable_grades_criteria';

INSERT IGNORE INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
           'grade_capability_display',
           '1',
           'Display Rubric Capability Grades on Grades Page',
           'If on, a table displaying all the capability grades students have received will be displayed on the students grade page',
           (SELECT id from lu_config_field WHERE field = 'boolean'),
           null,
           (SELECT id from lu_config_type WHERE type = 'LMS Policy'),
           'true',
           'false',
           'false',
           'settings'
       );

INSERT IGNORE INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES (
           'grade_progression_display',
           '1',
           'Display Student Progression Graph on Grades Page',
           'If on, a graph displaying a students progression over time across indicators will be displayed',
           (SELECT id from lu_config_field WHERE field = 'boolean'),
           null,
           (SELECT id from lu_config_type WHERE type = 'LMS Policy'),
           'true',
           'false',
           'false',
           'settings'
       );

