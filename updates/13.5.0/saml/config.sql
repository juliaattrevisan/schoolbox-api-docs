
INSERT INTO `config` (
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
  'saml_sso_url',
  '',
  'Single Sign-On URL',
  'The URL to direct single sign-on requests to.',
  'input',
  NULL,
  'Security',
  'true',
  'false',
  'false'
), (
  'saml_idp_certificate',
  '',
  'Single Sign-On IDP certificate',
  'The public key certificate that is used to encode single sign-on requests.',
  'textarea',
  NULL,
  'Security',
  'true',
  'false',
  'false'
);