/* Make a backup of user.year to user._year
 */
ALTER TABLE `user`
    ADD COLUMN `_year` VARCHAR(255) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL AFTER `year`;

UPDATE `user`
SET `_year` = `year`;

/* If user.year is an empty string, force it into NULL
 * since a) we don't know what that value was meant to be and b) we're adding FKs later
 * and I don't want a lu_year_level that corresponds to an empty string
 */
UPDATE `user`
SET `year` = NULL
WHERE `year` = '';

/* Get a list of existing lu_year_level.name rows
 * Get a list of current user.year rows
 *
 * Find new lu_year_level entries via user.year values by diffing these two sets
 */
-- SELECT `user`.id, `user`.`year`
-- FROM `user`
--          LEFT JOIN lu_year_level ON `user`.`year` = lu_year_level.`name`
-- WHERE lu_year_level.id IS NULL
--   AND `user`.`year` IS NOT NULL
--   AND `user`.`year` != ''

/* Only add the ones that don't already exist
 * Set all the checkboxes on by default
 */
INSERT INTO lu_year_level (`name`, `created_at`, `updated_at`, `sequence`) (
    SELECT `user`.`year`, NOW(), NOW(), 0
    FROM `user`
    LEFT JOIN lu_year_level lyl ON `user`.`year` = lyl.`name` AND lyl.deleted_at IS NULL
    WHERE lyl.id IS NULL
      AND `user`.`year` IS NOT NULL
      AND `user`.`year` != ''
    GROUP BY `year`
);

/* Try to resequence the newly-added rows (they'll have sequence 0)
 * Adapted from https://stackoverflow.com/questions/53728133/mysql-update-column-with-sequential-numeric-value-if-null-continued-sequence
 *
 * Find the biggest lu_year_level.sequence value among all the non-deleted records,
 * use that as a starting point to resequence all the 0-sequence rows we just added
 *
 * Natural Sorting is something of an adventure in mysql land so users should resort
 * their lookup according to taste
 */
UPDATE lu_year_level
JOIN (SELECT @rank := (SELECT MAX(T.sequence) FROM lu_year_level AS T WHERE deleted_at IS NULL)) r
SET sequence=@rank:=@rank+1
WHERE lu_year_level.sequence = 0;

/* Update everyone's user.year with an id from lu_year_level where
 * user.year == lu_year_level.name, via a join update
 */
-- SELECT
--    u.`year`, u.`_year`,
--    lyl.id, lyl.`name`
-- FROM `user` u
-- INNER JOIN lu_year_level lyl ON u.`year` = lyl.`name`;

UPDATE `user` u
INNER JOIN lu_year_level lyl ON u.`_year` = lyl.`name` AND lyl.deleted_at IS NULL
SET u.`year` = lyl.id;

/* Convert user.year into something compatible with lu_year_level.id
 */
ALTER TABLE `user`
    CHANGE COLUMN `year` `year_id` INT(10) UNSIGNED NULL DEFAULT NULL;

/* Add foreign key to user.year
 */
ALTER TABLE `user`
    ADD INDEX `fk_user_year_id_idx` (`year_id` ASC);

ALTER TABLE `user`
    ADD CONSTRAINT `fk_user_year_id`
        FOREIGN KEY (`year_id`)
            REFERENCES `lu_year_level` (`id`)
            ON DELETE NO ACTION
            ON UPDATE NO ACTION;
