-- Unable to recover if report does not exist
DELETE rg FROM pastoral_report_groups rg
  LEFT JOIN pastoral_report r ON r.id = rg.report_id
  WHERE r.id IS NULL;

-- Ensure report groups belong to a report
ALTER TABLE pastoral_report_groups
  DROP FOREIGN KEY pastoral_report_groups_pastoral_report_id_fk;

ALTER TABLE pastoral_report_groups
  CHANGE COLUMN report_id report_id INT(11) NOT NULL;

ALTER TABLE pastoral_report_groups
  ADD CONSTRAINT pastoral_report_groups_pastoral_report_id_fk
  FOREIGN KEY (`report_id`) REFERENCES pastoral_report(`id`)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT;

-- Unable to recover if group does not exist
DELETE rg FROM pastoral_report_groups rg
  LEFT JOIN pastoral_group g ON g.id = rg.group_id
  WHERE g.id IS NULL;

-- Ensure report groups belong to a group
ALTER TABLE pastoral_report_groups
  DROP FOREIGN KEY pastoral_report_groups_pastoral_group_id_fk;

ALTER TABLE pastoral_report_groups
  CHANGE COLUMN group_id group_id INT(11) NOT NULL;

ALTER TABLE pastoral_report_groups
  ADD CONSTRAINT pastoral_report_groups_pastoral_group_id_fk
  FOREIGN KEY (`group_id`) REFERENCES pastoral_group(`id`)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT;

-- This should not allow NULLs
UPDATE pastoral_report_groups
SET created_at = '0000-00-00 00:00:00'
WHERE created_at IS NULL;

ALTER TABLE pastoral_report_groups
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;


