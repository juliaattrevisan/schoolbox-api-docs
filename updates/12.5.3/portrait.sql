/* Remove unneeded portrait_updated_at column from user table
 * We can use filemtime instead to get this as needed
 */
ALTER TABLE `user` DROP COLUMN `portrait_updated_at`;