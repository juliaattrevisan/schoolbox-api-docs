-- Rename corrupted integer tags to what they should be
UPDATE pastoral_tags AS p1
INNER JOIN pastoral_tags AS p2 ON p1.id = p2.name
SET p2.`name` = p1.`name`;

-- Move this table somewhere else for now
-- we will restore records from here
RENAME TABLE `pastoral_tags` TO `pastoral_tags_deprecated`;

-- Recreate better pastoral_tags table
CREATE TABLE `pastoral_tag`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255) NOT NULL, `created_at` DATETIME, `updated_at` DATETIME, `deleted_at` DATETIME, PRIMARY KEY (`id`) );

-- Respawn a lookup of unique pastoral tags that are currently in use
-- Give them arbitrary created/updated at dates which previously didn't exist
INSERT INTO pastoral_tag (`name`, created_at, updated_at, deleted_at)
SELECT
`name`, NOW(), NOW(), NULL
FROM pastoral_tags_deprecated
WHERE deleted_at IS NULL
GROUP BY `name`
ORDER BY `id`;

-- Spawn a relation table for pastoral records and their tags
CREATE TABLE `pastoral_record_tag`( `pastoral_id` INT(11) NOT NULL, `tag_id` INT(11) NOT NULL );

-- Pastoral record/tag relations are unique
-- You shouldn't have the same tag assigned multiple times to a given record
ALTER TABLE `pastoral_record_tag` ADD UNIQUE INDEX `pastoral_id_tag_id` (`pastoral_id`, `tag_id`);

-- Reassign pastoral tags to their actual records
INSERT INTO pastoral_record_tag (pastoral_id, tag_id)
SELECT d.`pastoral_id`, t.`id`
FROM pastoral_tags_deprecated d
INNER JOIN pastoral_tag t ON d.name = t.name
AND t.deleted_at IS NULL;