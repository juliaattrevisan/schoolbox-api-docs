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


