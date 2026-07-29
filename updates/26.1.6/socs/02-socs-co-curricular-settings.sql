SET @socs_config_id = (SELECT id FROM lu_config_type WHERE type = 'SOCS');
SET @co_curricular_exists = (
    SELECT COUNT(*)
    FROM config
    WHERE name = 'socs_import_co_curricular'
);
SET @co_curricular_sequence = COALESCE(
    (SELECT sequence + 1 FROM config WHERE name = 'socs_import_sports_fixtures'),
    (SELECT COALESCE(MAX(sequence), 0) + 1 FROM config WHERE type_id = @socs_config_id)
);

-- Create a sequence hole immediately after the sports fixture toggle.
UPDATE config
SET sequence = sequence + 1
WHERE type_id = @socs_config_id
  AND sequence >= @co_curricular_sequence
  AND @co_curricular_exists = 0;

INSERT IGNORE INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES (
    'socs_import_co_curricular',
    '0',
    'Import Co-Curricular',
    'If ON, SOCS co-curricular clubs, events, and registrations will be imported.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    @socs_config_id,
    'true',
    'false',
    'false',
    'settings',
    @co_curricular_sequence
);
