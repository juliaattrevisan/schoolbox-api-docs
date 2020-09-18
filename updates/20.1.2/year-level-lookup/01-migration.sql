/* Make a backup of user.year to user._year
 */
ALTER TABLE `user`
    ADD COLUMN `_year` VARCHAR(255) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL AFTER `year`;

UPDATE `user`
SET `_year` = `year`;

/* Get a list of existing lu_year_level.name rows
 */

/* Get a list of current user.year rows
 */

/* Find new lu_year_level entries via user.year values by diffing these two sets
 */

/* Only add the ones that don't already exist
 * Set all the checkboxes on by default
 */

/* Update everyone's user.year with an id from lu_year_level where
 * user.year == lu_year_level.name, via a join update
 */

/* Add foreign key to user.year
 */
