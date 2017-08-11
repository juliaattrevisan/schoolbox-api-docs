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

-- Logically should not permit nulls (preserve old default in case it had some semantics)
UPDATE pastoral
  SET created_at = '0000-00-00 00:00:00'
  WHERE created_at IS NULL;
ALTER TABLE pastoral
  CHANGE COLUMN created_at created_at DATETIME NOT NULL;




