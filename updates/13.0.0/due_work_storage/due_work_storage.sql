CREATE TABLE submission_box_file (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  storage_id INT UNSIGNED NOT NULL,
  box_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (id)
);
ALTER TABLE submission_box_file ADD INDEX (storage_id);
ALTER TABLE submission_box_file ADD INDEX (box_id);
ALTER TABLE submission_box_file ADD UNIQUE INDEX (storage_id, box_id);