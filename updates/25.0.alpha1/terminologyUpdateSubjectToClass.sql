-- To Update around language of 'Subject'

UPDATE config
SET
    title = 'Enable Comments on Class Grades Pages',
    description = 'If toggled to ‘ON’, users are able to write and view comments on class grades pages.'
WHERE
    name = 'grade_comments_enable';

UPDATE config
SET
    title = 'Display Assessment Graph on Class Grades Page By Default',
    description = 'If toggled to ‘ON’, the box plot graph will be displayed on students’ class grades pages. The year level of the class page and the user’s profile setup may also affect the visibility. The graph is always visible to staff.'
WHERE
    name = 'display_grade_graph';

UPDATE config
SET
    title = 'Display Progression Graph on Class Grades Page',
    description = 'If toggled to ‘ON’, students‘ class grades pages will display a line tracking the student‘s achievement on each rubric indicator within the academic period. The year level of the class page may also affect the visibility.'
WHERE
    name = 'grade_progression_display';

UPDATE config
SET
    title = 'Display Capabilities and Indicators on Class Grades Pages',
    description = 'If toggled to ‘ON’, assessed rubric capabilities and indicators will be displayed on a students’ class grades pages. The year level of the class page may also affect the visibility.'
WHERE
    name = 'enable_grades_criteria';

UPDATE config
SET
    title = 'Display Capability Marks on Class Grades Page',
    description = 'If toggled to ‘ON’, attainment for each rubric capability will be displayed on students’ class grades pages. The year level of the class page may also affect the visibility.'
WHERE
    name = 'grade_capability_display';

UPDATE config
SET
    title = 'Synergetic Filetypes to filter classes by',
    description = 'Insert the filetypes that will be displayed in Schoolbox to enable filtering.'
WHERE
    name = 'external_synergetic_allowed_file_types';

UPDATE config
SET
    title = 'Show All Classes on Profile',
    description = 'If toggled to ‘ON’, all enrolled classes will be shown on a Student Profile page, regardless of its existence in Schoolbox.'
WHERE
    name = 'show_all_subjects';
