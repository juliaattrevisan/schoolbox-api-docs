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
  'site_analytics',
  '',
  'Analytics Tracking Code',
  'Provide the tracking code for use with your analytics tracking package.  This may be Google Analytics javascript code or other web tracking software.',
  (select id from lu_config_field where field='textarea'),
  '',
  (select id from lu_config_type where type='Site'),
  'true',
  'false',
  'true'
);
