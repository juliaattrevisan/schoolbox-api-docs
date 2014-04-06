INSERT INTO `config` (
  `name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `required`
) VALUES
('digest_oldest_first', '1',           'Digest Sort Descending', 'If set, digest emails will be sorted oldest to newest, otherwise newest to oldest', '3', '', '5', 'true', 'false'),
('digest_subject',      'News Digest', 'Digest Email Subject',   'The subject of digest emails',                                                      '1', '', '5', 'true', 'true'),
('messaging_enabled',   '1',            'Messaging Enabled',     'Is the messaging system enabled',                                                   '3', '', '5', 'true', 'false')
ON DUPLICATE KEY UPDATE `configID` = `configID`;

