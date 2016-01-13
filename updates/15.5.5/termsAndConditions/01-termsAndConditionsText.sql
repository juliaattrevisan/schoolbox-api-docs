INSERT IGNORE INTO `config` (
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
) VALUES (
    'login_terms_and_conditions',
    '',
    'Terms and Conditions for Login',
    'If you include text here, a checkbox will show on the login screen, with this label text - the user must check this in order to log in',
    (select id from lu_config_field where field='html'),
    '',
    (select id from lu_config_type where type='Security'),
    'true',
    'false',
    'false'
), (
    'login_terms_and_conditions_error',
    '',
    'Terms and Conditions Error Message',
    'The following error text will be shown if a user doesn\'t check the "Terms and Conditions for Login" checkbox on login',
    (select id from lu_config_field where field = 'input'),
    '',
    (select id from lu_config_type where type = 'Security'),
    'true',
    'false',
    'false'
);
