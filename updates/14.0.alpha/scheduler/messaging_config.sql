INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`, `required`
) VALUES
('websocket_path',      '/websocket',  'Websocket Listening Path', 'If set, the path on which messages will be listened for. Only necessary if intending to use port 80 for sockets', '1', '', '5', 'true', 'true', 'false'),
('websocket_port',      '8080',        'Websocket Port',           'The port on which Messaging listens to websockets (defaults to 8080)',                                            '1', '', '5', 'true', 'true', 'true'),
('websocket_port_ssl',  '8081',        'Websocket SSL Port',       'The port on which Messaging listens to secure websockets (defaults to 8081)',                                     '1', '', '5', 'true', 'true', 'true'),
('digest_oldest_first', '1',           'Digest Sort Descending',   'If set, digest emails will be sorted oldest to newest, otherwise newest to oldest',                               '3', '', '5', 'true', 'false', 'false'),
('digest_subject',      'News Digest', 'Digest Email Subject',     'The subject of digest emails',                                                                                    '1', '', '5', 'true', 'false', 'true'),
('messaging_enabled',   '1',           'Messaging Enabled',        'Is the messaging system enabled',                                                                                 '3', '', '5', 'true', 'false', 'false')
ON DUPLICATE KEY UPDATE `configID` = `configID`;
