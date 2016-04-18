ALTER TABLE submission_box_edumate_task RENAME TO submission_box_synergetic_task;
ALTER TABLE submission_return_edumate_task RENAME TO submission_return_synergetic_task;
INSERT INTO lu_submission_type (name) VALUES ('Task from Synergetic');
