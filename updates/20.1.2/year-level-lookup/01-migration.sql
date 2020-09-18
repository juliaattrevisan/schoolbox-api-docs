/* Make a backup of user.year to user._year
 */
ALTER TABLE `user`
    ADD COLUMN `_year` VARCHAR(255) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL AFTER `year`;

UPDATE `user`
SET `_year` = `year`;

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
    LEFT JOIN lu_year_level ON `user`.`year` = lu_year_level.`name`
    WHERE lu_year_level.id IS NULL
      AND `user`.`year` IS NOT NULL
      AND `user`.`year` != ''
);

/* Update everyone's user.year with an id from lu_year_level where
 * user.year == lu_year_level.name, via a join update
 */

/* Add foreign key to user.year
 */
