INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `required`
) VALUES
('digest_oldest_first', '1',           'Digest Sort Descending',   'If set, digest emails will be sorted oldest to newest, otherwise newest to oldest',                               '3', '', '5', 'true', 'false'),
('websocket_path',      '',            'Websocket Listening Path', 'If set, the path on which messages will be listened for. Only necessary if intending to use port 80 for sockets', '3', '', '5', 'true', 'false'),
('websocket_port',      '8080',        'Websocket Listening Port', 'The port on which Messaging listens to websockets (defaults to 8080/8081:SSL',                                    '3', '', '5', 'true', 'true'),
('digest_oldest_first', '1',           'Digest Sort Descending',   'If set, digest emails will be sorted oldest to newest, otherwise newest to oldest',                               '3', '', '5', 'true', 'false'),
('digest_subject',      'News Digest', 'Digest Email Subject',     'The subject of digest emails',                                                                                    '1', '', '5', 'true', 'true'),
('messaging_enabled',   '1',            'Messaging Enabled',       'Is the messaging system enabled',                                                                                 '3', '', '5', 'true', 'false')
ON DUPLICATE KEY UPDATE `configID` = `configID`;

