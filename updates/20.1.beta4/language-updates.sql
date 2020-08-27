UPDATE config SET options = 'list;List (left justified images):list-reversed;List (right justified images):card;Card' WHERE name like 'comms_news_article_list_layout';

UPDATE config SET title='Allow staff to delete feedback and grades', description='If toggled to \'ON\', staff can delete their own historically published grades and feedback from a student’s due work. If toggled to ‘OFF’, only superusers are able to delete feedback and grades.' WHERE name like 'lms_permit_staff_feedback_delete';


UPDATE config SET title='Display Overall Grade to Students and Parents' WHERE name like 'show_overall_grade';
UPDATE config SET title='Display Assessment Graph on Grades Page By Default' WHERE name like 'display_grade_graph';
UPDATE config SET title='Display Progression Graph on Grades Page' WHERE name like 'grade_progression_display';
UPDATE config SET title='Display Capabilities and Indicators on Grades Pages' WHERE name like 'enable_grades_criteria';
UPDATE config SET title='Display Capability Marks on Grades Page' WHERE name like 'grade_capability_display';

UPDATE config SET hidden='false' WHERE name like 'grades_enabled';
UPDATE config SET hidden='false' WHERE name like 'upcoming_assessments_enabled';
