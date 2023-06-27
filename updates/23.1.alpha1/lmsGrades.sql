### LMS settings now display correct tooltips as year levels can now affect these options.

UPDATE config
    set description = "If toggled to 'ON', the box plot graph can be displayed on a student's grades page. The year level of the class page and the user's profile setup may also affect the visibility. The graph is always visible to staff."
where name = 'display_grade_graph';

UPDATE config
set description = "If toggled to 'ON', assessed Capabilities and Indicators will be displayed on subject grades pages. The year level of the class page may also affect the visibility."
where name = 'enable_grades_criteria';

UPDATE config
set description = "If toggled to 'ON', attainment for each rubric capability will be displayed on the subject grades pages. The year level of the class page may also affect the visibility."
where name = 'grade_capability_display';

UPDATE config
set description = "If toggled to 'ON', the students' grade pages will display a line tracking a students achievement on each rubric indicator within the academic period. The year level of the class page may also affect the visibility."
where name = 'grade_progression_display';
