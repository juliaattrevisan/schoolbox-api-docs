-- Make pastoral.owner_id relationship mandatory
ALTER TABLE pastoral
  DROP FOREIGN KEY fk_pastoral_owner_id;

ALTER TABLE pastoral
    CHANGE COLUMN owner_id owner_id INT(11) UNSIGNED NOT NULL;

ALTER TABLE pastoral
  ADD CONSTRAINT fk_pastoral_owner_id
  FOREIGN KEY (`owner_id`)
  REFERENCES user(`id`)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT;


-- Make pastoral.type_id relationship mandatory
-- (at this point we have run a script to ensure all are linked correctly)
ALTER TABLE pastoral
  DROP FOREIGN KEY fk_pastoral_type;

ALTER TABLE pastoral
  CHANGE COLUMN type_id type_id INT(11) NOT NULL;

ALTER TABLE pastoral
  ADD CONSTRAINT fk_pastoral_type
FOREIGN KEY (`type_id`)
REFERENCES lu_pastoral_type(`id`)
  ON DELETE RESTRICT
  ON UPDATE RESTRICT;

-- This field is no longer mapped in 17.0
ALTER TABLE pastoral
  CHANGE COLUMN private _private TINYINT(4) DEFAULT 0 NULL COMMENT 'deprecated in 17.0';

-- Should never be NULL
UPDATE pastoral
SET heading = ''
WHERE heading IS NULL;

ALTER TABLE pastoral
    CHANGE COLUMN heading heading VARCHAR(255) NOT NULL;

-- Should never be NULL
UPDATE pastoral
SET body = ''
WHERE body IS NULL;

ALTER TABLE pastoral
  CHANGE COLUMN body body TEXT NOT NULL;

-- Logically should not permit nulls (preserve old default in case it had some semantics)
UPDATE pastoral
  SET created_at = '1970-01-01 00:00:00'
  WHERE created_at IS NULL;
ALTER TABLE pastoral
  CHANGE COLUMN created_at created_at DATETIME NOT NULL AFTER modified_by_user_id;

-- Logically should not permit nulls (preserve old default in case it had some semantics)
UPDATE pastoral
  SET updated_at = '1970-01-01 00:00:00'
  WHERE updated_at IS NULL;
ALTER TABLE pastoral
  CHANGE COLUMN updated_at updated_at DATETIME NOT NULL AFTER created_at;

-- Move to end to conform to conventions
ALTER TABLE pastoral
  CHANGE COLUMN deleted_at deleted_at DATETIME NULL AFTER updated_at;

-- Code usages indicates this should permit NULL
ALTER TABLE pastoral
  CHANGE COLUMN points points FLOAT DEFAULT 0 NULL AFTER body;

-- Must run after the corresponding PHP script to ensure this field is valid
ALTER TABLE pastoral
  CHANGE COLUMN modified_by_user_id modified_by_user_id INT(11) UNSIGNED NULL;

ALTER TABLE pastoral
  ADD CONSTRAINT fk_pastoral_modified_by_user_id
  FOREIGN KEY (`modified_by_user_id`)
  REFERENCES user(`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;





