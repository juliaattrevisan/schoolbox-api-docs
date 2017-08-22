-- If no owner, assign to 'alaress' user
UPDATE socialstream_post p
  LEFT JOIN user u ON u.id = p.owner_id
SET p.owner_id = (SELECT id FROM user WHERE username LIKE 'alaress')
WHERE u.id IS NULL;

-- Upgrade relationship from optional to mandatory
ALTER TABLE socialstream_post
  DROP FOREIGN KEY fk_socialstream_post_user_id;

ALTER TABLE socialstream_post
  CHANGE COLUMN owner_id owner_id INT(11) UNSIGNED NOT NULL;

ALTER TABLE socialstream_post
  ADD CONSTRAINT fk_socialstream_post_user_id
FOREIGN KEY (owner_id) REFERENCES user(id)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;

