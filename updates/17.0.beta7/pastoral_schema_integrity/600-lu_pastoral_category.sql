-- 'pastoral category' === 'pastoral sub-type'
-- You cannot have a 'sub-type' without a 'type'
-- Make lu_pastoral_category.pastoral_type_id mandatory


set @var=if((SELECT true FROM information_schema.TABLE_CONSTRAINTS WHERE
  CONSTRAINT_SCHEMA = DATABASE() AND
  TABLE_NAME        = 'lu_pastoral_category' AND
  CONSTRAINT_NAME   = 'fk_lu_pastoral_category_pastoral_type_id' AND
  CONSTRAINT_TYPE   = 'FOREIGN KEY') = true,
            'ALTER TABLE lu_pastoral_category DROP FOREIGN KEY fk_lu_pastoral_category_pastoral_type_id;',
            'select 1'
);

PREPARE stmt FROM @var;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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





