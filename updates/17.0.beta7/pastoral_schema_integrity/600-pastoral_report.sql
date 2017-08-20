-- Make pastoral_report.owner_id relationship mandatory
ALTER TABLE pastoral_report
  DROP FOREIGN KEY pastoral_report_user_id_fk;

ALTER TABLE pastoral_report
  CHANGE COLUMN owner_id owner_id INT(4) UNSIGNED NOT NULL;

ALTER TABLE pastoral_report
  ADD CONSTRAINT pastoral_report_user_id_fk
  FOREIGN KEY (`owner_id`)
  REFERENCES user(`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;

-- No NULL names
UPDATE pastoral_report
  SET name = CONCAT('Report #', id)
  WHERE name IS NULL;

ALTER TABLE pastoral_report
  CHANGE COLUMN name name VARCHAR(255) NOT NULL;

-- This should not allow NULLs
UPDATE pastoral_report
SET created_at = '1970-01-01 00:00:00'
WHERE created_at IS NULL;

ALTER TABLE pastoral_report
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;

-- This should not allow NULLs
UPDATE pastoral_report
SET updated_at = '1970-01-01 00:00:00'
WHERE updated_at IS NULL;

ALTER TABLE pastoral_report
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL;


