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
  'login_anonymous',
  '',
  'Anonymous Login Username',
  'If set, visiting /account/anonymous.php will auto-log you in as this user. Ensure user has very low permissions and has the guest role, this could potentially be very dangerous.',
  (select id from lu_config_field where field='input'),
  '',
  (select id from lu_config_type where type='Security'),
  'true',
  'true',
  'false'
);
