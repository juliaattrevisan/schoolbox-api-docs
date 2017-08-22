-- If a wiki-revision's author is hard-deleted, then set the author to the alaress user
UPDATE wikiRevision wr
  LEFT JOIN user u ON u.id = wr.userID
SET userID = (SELECT id FROM user WHERE username LIKE 'alaress')
WHERE u.id IS NULL;

-- Upgrade revision-to-wiki relationship from optional to mandatory
ALTER TABLE wikiRevision
  DROP FOREIGN KEY fk_wikiRevision_user_id;

ALTER TABLE wikiRevision
  CHANGE COLUMN userID userID INT(11) UNSIGNED NOT NULL;

ALTER TABLE wikiRevision
  ADD CONSTRAINT fk_wikiRevision_user_id
FOREIGN KEY (userID) REFERENCES user(id)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;
