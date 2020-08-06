CREATE TABLE IF NOT EXISTS `storage_transform` (
    `hash` VARCHAR(100) NULL COLLATE 'ascii_general_ci',
    `transform` VARCHAR(100) NULL,
    `size` INT(11) NULL,
    UNIQUE INDEX `unique_hash_transform` (`hash` ASC, `transform` ASC),
    INDEX `index_hash` (`hash` ASC)
#     CONSTRAINT `fk_storage_transform` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE CASCADE ON UPDATE CASCADE
)
