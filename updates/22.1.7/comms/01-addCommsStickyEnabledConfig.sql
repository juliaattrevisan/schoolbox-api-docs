# Add priority news enable setting at position 4
UPDATE config SET sequence = sequence + 1 WHERE module = 'comms' AND sequence >= 4;
INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    options,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'comms_sticky_enabled',
    '1',
    'Enable Priority News',
    'If toggled to ''ON'', news moderators will be allowed to create priority articles.\n\n(These articles will be pinned to the top of the news feed, and audience members will be directly notified via email, push and instant notifications regardless of settings.)',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    null,
    'true',
    'false',
    'false',
    'comms',
    4
);
