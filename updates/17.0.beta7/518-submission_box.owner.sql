-- If a submission_box owner is hard-deleted, then set the author to the alaress user
UPDATE submission_box sb
  LEFT JOIN user u ON u.id = sb.owner
SET owner = (SELECT id FROM user WHERE username LIKE 'alaress')
WHERE u.id IS NULL;

-- Ensure referential integrity
ALTER TABLE submission_box
  CHANGE COLUMN owner owner INT(11) UNSIGNED NOT NULL;

ALTER TABLE submission_box
  ADD CONSTRAINT fk_submission_box_owner
FOREIGN KEY (owner) REFERENCES user(id)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;
