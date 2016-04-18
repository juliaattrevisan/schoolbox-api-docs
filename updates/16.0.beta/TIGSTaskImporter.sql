ALTER TABLE submission_box_edumate_task RENAME TO submission_box_synergetic_task;
ALTER TABLE submission_return_edumate_task RENAME TO submission_return_synergetic_task;
UPDATE lu_submission_type SET name = 'Task from Synergetic' WHERE name = 'Task from Edumate';
