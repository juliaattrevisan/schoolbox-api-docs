-- Delete any post if not part of a thread
DELETE FROM forumPost WHERE threadID IS NULL;

-- Upgrade relationship from optional to mandatory
ALTER TABLE forumPost
  DROP FOREIGN KEY fk_forumPost_thread_id;

ALTER TABLE forumPost
  CHANGE COLUMN threadID threadID INT(11) NOT NULL;

ALTER TABLE forumPost
  ADD CONSTRAINT fk_forumPost_thread_id
FOREIGN KEY (threadID) REFERENCES forumThread(threadID)
  ON UPDATE NO ACTION
  ON DELETE NO ACTION;
