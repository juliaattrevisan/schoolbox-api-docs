INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	'external_tass_commrules',
	'0',
	'Enable TASS Communication Rules',
	'Allows using the optional communication rules module in TASS to define extra guardian to student relationships',
	'3',
	'',
	'7',
	'true',
	'false',
	'false'
);
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	'external_tass_commrules_addnum',
	'',
	'TASS Communication Rules Address Number Filter',
	'Address Number (add_num) block IDs to use for guardian relationships with students as a comma separated list. E.g. 1,2,4',
	'1',
	'',
	'7',
	'true',
	'false',
	'false'
);
INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`)
VALUES (
	'external_tass_commrules_typecode',
	'',
	'TASS Communication Rules Type Code Filter',
	'Communication Rules Type Codes to use for guardian relationships with students as a comma separated list. E.g. TK,TKCO,LW',
	'1',
	'',
	'7',
	'true',
	'false',
	'false'
);
