-- If a quiz owner is hard-deleted, then set the author to the alaress user
UPDATE assess q
  LEFT JOIN user u ON u.id = q.user_id
SET user_id = (SELECT id FROM user WHERE username LIKE 'alaress')
WHERE u.id IS NULL;

-- Ensure referential integrity
ALTER TABLE assess
  CHANGE COLUMN user_id user_id INT(11) UNSIGNED NOT NULL;

ALTER TABLE assess
  ADD CONSTRAINT fk_assess_user_id
FOREIGN KEY (user_id) REFERENCES user(id)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;
