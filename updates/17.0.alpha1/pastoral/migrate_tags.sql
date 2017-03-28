-- Rename corrupted integer tags as what they should be
UPDATE pastoral_tags AS p1
INNER JOIN pastoral_tags AS p2 ON p1.id = p2.name
SET p2.`name` = p1.`name`

-- Move this table somewhere else for now
-- we will restore records from here
RENAME TABLE `pastoral_tags` TO `schoolbox`.`pastoral_tags_deprecated`;

-- Recreate better pastoral_tags table
CREATE TABLE `pastoral_tags`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255) NOT NULL, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );