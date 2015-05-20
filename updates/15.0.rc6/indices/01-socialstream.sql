-- Socialstream indices
ALTER TABLE `socialstream_post`
    ADD INDEX `owner` (`owner_id`),
    ADD INDEX `homepage` (`homepage_id`),
    ADD INDEX `cache_hash` (`cache_hash`),
    ADD INDEX `root_id` (`root_id`),
    ADD INDEX `parent_id` (`parent_id`)
;

/**
 * Foreign key relations excluded because they require setting all parent and
 * root ID values to NULL if they don't refer to an existing post ID.
 */
/**
UPDATE `socialstream_post` `sp`
    LEFT JOIN `socialstream_post` `parent` ON `sp`.`parent_id` = `parent`.`post_id`
SET `sp`.`parent_id` = NULL
WHERE `parent`.`post_id` IS NULL;

UPDATE `socialstream_post` `sp`
    LEFT JOIN `socialstream_post` `root` ON `sp`.`root_id` = `root`.`post_id`
SET `sp`.`root_id` = NULL
WHERE `root`.`post_id` IS NULL;

ALTER TABLE `socialstream_post`
    ADD FOREIGN KEY (`parent_id`) REFERENCES `socialstream_post`(`post_id`)
        ON UPDATE CASCADE ON DELETE SET NULL,
    ADD FOREIGN KEY (`root_id`) REFERENCES `socialstream_post`(`post_id`)
        ON UPDATE CASCADE ON DELETE SET NULL
;
 */