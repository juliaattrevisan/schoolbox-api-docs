update `config` set `title` = 'Display Grade Graph on Student Grade Card', 
  `description` = 'This will show the grade graph on the student grade card to all users', `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where `name` = 'display_grade_graph';

update `config` set `title` = 'Hide Due Work from Parents', 
  `description` = 'This will prevent parents from being able to access the due work calendar for their students', `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where  `name` = 'restrict_parent_duework_access';

update `config` set `title` = 'Course Folder Location', 
  `description` = 'This folder will be used as the base for the course selector, this simplifies course selection', `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where `name` = 'course_homepage';

update `config` set `title` = 'Default Submission Grade Visibility', 
  `description` = 'This controls the default setting for grade visibility.  Note this is just a default and can be overridden per-submission.  Combine this with Maximum Submission visibility to control the default and what is shown', `field_id` = '7', `options` = '0;Staff Only:1;Staff and Students:2;Staff, Students and their Parents', `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where `name` = 'submission_access';

update `config` set `title` = 'Display Plagiarism Score to Students', 
  `description` = 'When students submit work this option will allow them to view the plagiarism score they received. Note that the plagiarism system must be enabled for this to work.',  `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where `name` = 'plagscan_student_visible';

update `config` set `title` = 'Display Grade Average to Students and Parents', 
  `description` = 'Disable the display of the overall grade on the Due Work and Grade Report page for students and parents.', `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where `name` = 'show_overall_grade';

update `config` set `title` = 'Display Class Average on Student Grade Graph', 
  `description` = 'The class average is shown as a blue line on the students grade report. This option allows you to hide that line from students and parents.', `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where `name` = 'display_grade_average';

update `config` set `title` = 'Hide Work Details from Parents', 
  `description` = 'This option will prevent parents from accessing the due work details, this includes files attached and the student submissions.  They will still be able to see the dates and titles for the work via the due work calendar.',  `type_id` = (SELECT `id` FROM lu_config_type WHERE `type` like 'LMS Policy')
where  `name` = 'restrict_student_submissions';
