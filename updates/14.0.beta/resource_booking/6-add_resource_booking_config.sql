INSERT INTO `config`(
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
  'resource_booking_enabled',
  '0',
  'Enable Resource Booking Module',
  'Is the resource booking system enabled?',
  3, -- boolean
  '',
  (SELECT `id` FROM lu_config_type WHERE `type` like 'Modules'),
  'false',
  'false',
  'false'
);
