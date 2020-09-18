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
INSERT INTO lu_year_level (`name`, `created_at`, `updated_at`) (
    SELECT `user`.`year`, NOW(), NOW()
    FROM `user`
    LEFT JOIN lu_year_level lyl ON `user`.`year` = lyl.`name` AND lyl.deleted_at IS NULL
    WHERE lyl.id IS NULL
      AND `user`.`year` IS NOT NULL
      AND `user`.`year` != ''
);

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
SET u.`year` = lyl.id

/* Add foreign key to user.year
 */
