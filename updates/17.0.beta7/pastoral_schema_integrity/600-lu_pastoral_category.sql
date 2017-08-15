-- 'pastoral category' === 'pastoral sub-type'
-- You cannot have a 'sub-type' without a 'type'
-- Make lu_pastoral_category.pastoral_type_id mandatory
ALTER TABLE lu_pastoral_category
  DROP FOREIGN KEY fk_lu_pastoral_category_pastoral_type_id;

ALTER TABLE lu_pastoral_category
  CHANGE COLUMN name name VARCHAR(100) NOT NULL,
  CHANGE COLUMN pastoral_type_id pastoral_type_id INT(11) NOT NULL;

ALTER TABLE lu_pastoral_category
  ADD CONSTRAINT fk_lu_pastoral_category_pastoral_type_id
  FOREIGN KEY (`pastoral_type_id`)
  REFERENCES lu_pastoral_type(`id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT;

-- This field is deprecated and not mapped
ALTER TABLE lu_pastoral_category
    CHANGE external_id _external_id INT(11) NULL COMMENT 'deprecated in 17.0';





