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
  '1',
  'Enable Resource Booking Module',
  'Is the resource booking system enabled?',
  3, -- boolean
  '',
  11, -- modules
  'false',
  'true',
  'false'
);
