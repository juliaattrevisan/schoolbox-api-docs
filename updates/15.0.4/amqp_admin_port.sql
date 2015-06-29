/* AMQP connection defaults */
INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES
  ('amqp_admin_port', '15672',     'Message Queue Admin Port', 'The message queue admin subscription port', '1', '', (SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'), 'true', 'true', 'true')
ON DUPLICATE KEY UPDATE `configID` = `configID`;
