ALTER TABLE submission_box_file
  CHANGE `storage_id` `storage_id` VARCHAR(40) NOT NULL;

INSERT INTO submission_box_file
  SELECT f.id, s.hash, f.box_id
  FROM `storage` s
  INNER JOIN submission_box_file f ON f.storage_id = s.storage_id
ON DUPLICATE KEY UPDATE storage_id = s.hash;