-- No point keeping revisions if the record they relate to has been hard-deleted
DELETE r FROM pastoral_revision r
  LEFT JOIN pastoral p ON p.id = r.root_id
  WHERE p.id IS NULL;

-- Ensure revisions point to a pastoral record
ALTER TABLE pastoral_revision
  CHANGE COLUMN root_id root_id INT(11) NOT NULL;

ALTER TABLE pastoral_revision
  ADD CONSTRAINT fk_pastoral_revision_pastoral_id
  FOREIGN KEY (root_id) REFERENCES pastoral(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- Ensure pastoral_revision.owner referential integrity (must run after clean-up script)
ALTER TABLE pastoral_revision
  CHANGE COLUMN owner_id owner_id INT(4) UNSIGNED NOT NULL;

ALTER TABLE pastoral_revision
  ADD CONSTRAINT fk_pastoral_revision_owner_id
  FOREIGN KEY (owner_id) REFERENCES user(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- No point keeping revisions if student is hard-deleted
DELETE r FROM pastoral_revision r
  LEFT JOIN user u ON u.id = r.student_id
  WHERE u.id IS NULL;

ALTER TABLE pastoral_revision
  CHANGE COLUMN student_id student_id INT(11) UNSIGNED NOT NULL;

ALTER TABLE pastoral_revision
  ADD CONSTRAINT fk_pastoral_revision_student_id
  FOREIGN KEY (student_id) REFERENCES user(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- Ensure pastoral_revision.type referential integrity.
-- At this point, we have repaired any broken pastoral.type by inserting a new row.
-- If the revision's type is invalid, just point it to the pastoral.type
UPDATE pastoral_revision r
  LEFT JOIN lu_pastoral_type rt ON rt.id = r.type_id
  JOIN pastoral p ON p.id = r.root_id
  JOIN lu_pastoral_type pt ON pt.id = p.type_id
  SET r.type_id = p.type_id
  WHERE rt.id IS NULL;

-- Ensure we this can't lose integrity in the future
ALTER TABLE pastoral_revision
  DROP FOREIGN KEY fk_pastoral_revision_type;

ALTER TABLE pastoral_revision
  CHANGE COLUMN type_id type_id INT(11) NOT NULL;

ALTER TABLE pastoral_revision
  ADD CONSTRAINT fk_pastoral_revision_type
  FOREIGN KEY (type_id) REFERENCES lu_pastoral_type(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;


-- This field is no longer mapped in 17.0
ALTER TABLE pastoral_revision
  CHANGE COLUMN private _private TINYINT(4) DEFAULT 0 NULL COMMENT 'deprecated in 17.0';

-- Should never be NULL
UPDATE pastoral_revision
SET heading = ''
WHERE heading IS NULL;

ALTER TABLE pastoral_revision
  CHANGE COLUMN heading heading VARCHAR(255) NOT NULL;

-- Should never be NULL
UPDATE pastoral_revision
SET body = ''
WHERE body IS NULL;

ALTER TABLE pastoral_revision
  CHANGE COLUMN body body TEXT NOT NULL;

-- If NULL, set the revision's created timestamp to pastoral created timestamp
UPDATE pastoral_revision r
JOIN pastoral p ON p.id = r.root_id
SET r.created_at = p.created_at
WHERE r.created_at IS NULL;

ALTER TABLE pastoral_revision
  CHANGE COLUMN created_at created_at DATETIME NOT NULL AFTER modified_by_user_id;

-- This should not be null. Must run after the PHP script to fix the off-by-one timestamp issue.
ALTER TABLE pastoral_revision
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL AFTER created_at;

-- Just move column so timestamps are grouped at end
ALTER TABLE pastoral_revision
  CHANGE COLUMN deleted_at deleted_at DATETIME NULL AFTER updated_at;

-- If the revision.category (aka 'sub-type') is invalid, just set it to the pastoral.category_id
-- The pastoral.category_id may be NULL, that's fine. It's an optional relationship
UPDATE pastoral_revision r
LEFT JOIN lu_pastoral_category rc ON rc.id = r.category_id
JOIN pastoral p ON p.id = r.root_id
LEFT JOIN lu_pastoral_category pc ON pc.id = p.category_id
SET r.category_id = p.category_id
WHERE rc.id IS NULL;

ALTER TABLE pastoral_revision
  CHANGE COLUMN category_id category_id INT(11) NULL;

-- Add a FK to ensure integrity
ALTER TABLE pastoral_revision
  ADD CONSTRAINT fk_pastoral_revision_category_id
  FOREIGN KEY (category_id) REFERENCES lu_pastoral_category(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;

-- Ensure pastoral_revision.owner referential integrity
ALTER TABLE pastoral_revision
  CHANGE COLUMN modified_by_user_id modified_by_user_id INT(11) UNSIGNED NULL;

-- If pointing to hard-deleted user, set it to the current value on the pastoral record
-- The pastoral.modified_by_user_id may be NULL, that's fine. It's an optional relationship
UPDATE pastoral_revision r
JOIN pastoral p ON p.id = r.root_id
LEFT JOIN user ru ON ru.id = r.modified_by_user_id
SET r.modified_by_user_id = p.modified_by_user_id
WHERE ru.id IS NULL
AND r.modified_by_user_id IS NOT NULL;

ALTER TABLE pastoral_revision
  ADD CONSTRAINT fk_pastoral_revision_modified_by_user_id
  FOREIGN KEY (modified_by_user_id) REFERENCES user(id)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;







