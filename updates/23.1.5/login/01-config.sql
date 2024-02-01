-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'show_default_login',
    'Show default login fields',
    true,
    "Toggling this to ‘ON’ will show the username and password fields on the login screen. Set this to ‘OFF’ to hide them.",
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = 'Site'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'saml_login_title',
    'Title for Single Sign-On',
    'Login with Single Sign-On',
    '“Login with Single Sign-On” will show by default if left empty.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    (SELECT id FROM lu_config_type WHERE type = 'SAML'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'saml_login_icon',
    'Icon for Single Sign-On',
    null,
    'This icon will display to the left of the title.',
    (SELECT id FROM lu_config_field WHERE field = 'file'),
    (SELECT id FROM lu_config_type WHERE type = 'SAML'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'saml_login_colour',
    'Colour for Single Sign-On',
    'rgb(0, 102, 176, 0)',
    'Choose a colour for this login button.',
    (SELECT id FROM lu_config_field WHERE field = 'colour'),
    (SELECT id FROM lu_config_type WHERE type = 'SAML'),
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'oidc_login_title',
    'Title for OpenID Connect',
    'Login with OpenID Connect',
    '“Login with OpenID Connect” will show by default if left empty.',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'oidc_login_icon',
    'Icon for OpenID Connect',
    null,
    'This icon will display to the left of the title.',
    (SELECT id FROM lu_config_field WHERE field = 'file'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT MAX(sequence)+1 FROM `config` c)
);

-- Add new config
INSERT INTO config (`name`, `title`, `value`, `description`, `field_id`,  `type_id`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
    'oidc_login_colour',
    'Colour for OpenID Connect',
    'rgb(0, 102, 176, 0)',
    'Choose a colour for this login button.',
    (SELECT id FROM lu_config_field WHERE field = 'colour'),
    null,
    'true',
    'false',
    'false',
    'oauth',
    (SELECT MAX(sequence)+1 FROM `config` c)
);
