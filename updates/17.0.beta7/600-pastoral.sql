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
