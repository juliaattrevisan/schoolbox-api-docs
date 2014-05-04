/* AMQP connection defaults */
INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES
  ('amqp_host',       'localhost', 'Message Queue Host',       'The message queue hostname or IP address',                                '1', '', (SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'), 'true', 'true', 'true'),
  ('amqp_port',       '5672',      'Message Queue Port',       'The message queue port',                                                  '1', '', (SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'), 'true', 'true', 'true'),
  ('amqp_stomp_port', '61613',     'Message Queue STOMP Port', 'The message queue STOMP subscription port',                               '1', '', (SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'), 'true', 'true', 'true'),
  ('amqp_user',       'guest',     'Message Queue Username',   'The username Schoolbox uses to authenticate to the message queue server', '1', '', (SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'), 'true', 'true', 'true'),
  ('amqp_pass',       'guest',     'Message Queue Password',   'The password Schoolbox uses to authenticate to the message queue server', '1', '', (SELECT `id` FROM lu_config_type WHERE `type` like 'External DB'), 'true', 'true', 'true')
ON DUPLICATE KEY UPDATE `configID` = `configID`;

