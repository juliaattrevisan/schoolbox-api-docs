INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`)
 VALUES (
 'modules_parentdirectory_emailall',
 '0',
 'Allow Parents to Email All Parents',
 'This will allow parents to access the email all function of the parent directory.  Thus allowing parents to bulk email other parents.',
 '3',
 (SELECT `id` FROM lu_config_type WHERE `type` like 'Policy'),
 'true',
 'false'
 );
