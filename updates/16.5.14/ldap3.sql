INSERT INTO `config` (
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
    'ldap_host_ter',
    '',
    'LDAP Tertiary Host',
    'Address of LDAP server to which search should be performed',
    1,
    '',
    16,
    'true',
    'false',
    'false'
);

INSERT INTO `config` (
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
  'ldap_user_ter',
  '',
  'LDAP Tertiary Username',
  'User account for binding to LDAP, should be complete DN',
  1,
  '',
  16,
  'true',
  'false',
  'false'
);

INSERT INTO `config` (
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
  'ldap_base_ter',
  '',
  'LDAP Tertiary Search Base',
  'Base search path for username lookup',
  1,
  '',
  16,
  'true',
  'false',
  'false'
);
