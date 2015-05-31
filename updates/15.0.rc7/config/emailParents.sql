INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`)
 VALUES (
 'modules_parentdirectory_emailall',
 '0',
 'Allow Email All Parents from Parent Directory',
 'If enabled, staff and parents may bulk email parents from the parent directory. (This setting respects the parent\'s directory privacy setting.)',
 '3',
 (SELECT `id` FROM lu_config_type WHERE `type` like 'Policy'),
 'true',
 'false'
 );
