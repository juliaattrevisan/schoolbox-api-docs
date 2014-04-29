/* Add relation table
 */
CREATE TABLE `etherpad_groups`( `id` INT(11) NOT NULL AUTO_INCREMENT, `group_id` VARCHAR(255) COMMENT 'Etherpad Group ID', `user_id` INT(11), `homepage_id` INT(11), PRIMARY KEY (`id`) );