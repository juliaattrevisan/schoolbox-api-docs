-- Hard-delete any items pointing to non-existent submission boxes or users
DELETE FROM eportfolio_showcase_item
WHERE submission_box_id NOT IN (SELECT id FROM submission_box) OR uid NOT IN (SELECT id FROM user);

-- Prevent nulls, and add foreign key constraint to submissionBox
ALTER TABLE eportfolio_showcase_item
  MODIFY submission_box_id INT(6) UNSIGNED NOT NULL,
  MODIFY created_at DATETIME NOT NULL,
  MODIFY updated_at DATETIME NOT NULL,
  ADD CONSTRAINT eportfolio_showcase_item_submission_box_id_fk
    FOREIGN KEY (submission_box_id)
    REFERENCES submission_box (id),
  ADD CONSTRAINT eportfolio_showcase_item_user_id_fk
    FOREIGN KEY (uid)
    REFERENCES user (id);

