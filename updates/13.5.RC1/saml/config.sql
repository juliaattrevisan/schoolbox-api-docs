
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
  'saml_sso_url',
  '',
  'Single Sign-On URL',
  'The URL to direct single sign-on requests to.',
  1, -- 'input',
  NULL,
  2, -- 'Security',
  'true',
  'false',
  'false'
), (
  'saml_idp_certificate',
  '',
  'Single Sign-On IDP certificate',
  'The public key certificate that is used to encode single sign-on requests.',
  2, -- 'textarea',
  NULL,
  2, -- 'Security',
  'true',
  'false',
  'false'
);