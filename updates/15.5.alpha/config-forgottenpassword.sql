-- Config for chosing what to send for forgotten passwords
INSERT IGNORE INTO `config`(
	    `name`,
	    `value`,
	    `title`,
	    `description`,
	    `field_id`,
	    `options`,
	    `type_id`,
	    `editable`,
	    `hidden`,
	    `required`
)
VALUES (
	    'forgotten_password',
	    (IF ((SELECT COUNT(*) FROM `config` `c` WHERE `c`.`name` = 'site_location' AND `c`.`value` IN ('shore','sheldon') LIMIT 1), '1', '0')),
	    'Forgotten Password Email Content',
	    'Choose what information will be provided in the forgotten password email',
	    (select id from lu_config_field where field='selectKey'),
	    '0;Username and Password:1;Password Only',
	    (select id from lu_config_type where type='Policy'),
	    'true',
	    'false',
	    'true'
);
