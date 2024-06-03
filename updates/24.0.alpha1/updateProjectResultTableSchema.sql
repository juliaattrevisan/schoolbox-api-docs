ALTER TABLE project_result CHANGE COLUMN marker_id _marker_id int(11) DEFAULT NULL;

DELETE FROM project_result WHERE mark IS NULL OR norm_mark IS NULL;

ALTER TABLE project_result CHANGE COLUMN mark mark varchar(64) NOT NULL;
ALTER TABLE project_result CHANGE COLUMN norm_mark norm_mark decimal(14,11) NOT NULL;
