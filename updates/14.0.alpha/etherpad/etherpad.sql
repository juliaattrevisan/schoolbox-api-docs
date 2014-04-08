/* Add relation table
 */
CREATE TABLE `etherpad_groups`( `id` INT(11) NOT NULL AUTO_INCREMENT, `group_id` VARCHAR(255) COMMENT 'Etherpad Group ID', `user_id` INT(11), `homepage_id` INT(11), PRIMARY KEY (`id`) );

/* Add API key
 */
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`) VALUES ('etherpad_api_token', 'as90dfuasdifjsdopaifjuhas9p', 'Etherpad API Token', 'Used to access Etherpad documents', '1', '3', 'true');
