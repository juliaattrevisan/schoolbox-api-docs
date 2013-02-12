INSERT INTO `config`(
  `name`,
  `value`,
  `title`,
  `description`,
  `field`,
  `options`,
  `type`,
  `editable`,
  `hidden`,
  `required`
)
VALUES (
  'ldap_search_filter',
  '',
  'LDAP Search Filter',
  'Pre-filter to put onto LDAP login searches - e.g. objectClass=User',
  'input',
  '',
  'Security',
  'true',
  'false',
  'false'
);
