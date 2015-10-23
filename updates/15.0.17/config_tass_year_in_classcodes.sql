INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES  (
	'external_tass_year_in_class_code',
	(IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'site_location' AND `c`.`value` = 'meriden' LIMIT 1), '0', '1')),
	'TASS Use Year Level in Class Codes',
	'Use the year level in the class codes (i.e. 10-0045-A), deselect this to not use the year level in class codes (i.e. -0045-A)',
	(SELECT `id` FROM lu_config_field WHERE `field` like 'boolean'),
	'',
	(SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'),
	'true',
	(IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'external_type' AND `c`.`value` = 'tass' LIMIT 1), 'false', 'true')),
	'false'
)
ON DUPLICATE KEY UPDATE `configID` = `configID`;
