### PST tooltip fixes
UPDATE config
SET description = 'Insert the maximum number of levels available to use on rubrics across the whole instance.'
WHERE name = 'rubric_max_levels';

UPDATE config
SET description = 'This setting will be used to determined the default time that a learning activity is due. If left blank, the time on due work items will be set to 11:59pm.'
WHERE name = 'lms_duetime_default';

UPDATE config
SET description = 'This duration will automatically be applied to the due date on a student’s submission if ‘resubmit required’ is selected on a due work item on a class page.'
WHERE name = 'lms_resubmit_window';
