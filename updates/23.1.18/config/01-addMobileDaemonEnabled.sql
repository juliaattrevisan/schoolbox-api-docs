SELECT @configType := id FROM lu_config_type WHERE `type` = 'Modules';
INSERT INTO config (
    name,
    value,
    title,
    description,
    field_id,
    type_id,
    editable,
    hidden,
    required,
    module,
    sequence
) VALUES (
    'mobile_daemon_enabled',
    '0',
    'Enable Mobile Daemon',
    'If ON, process and send notifications to mobile devices via a specialised daemon process, rather than within the schedule daemon. (This setting is for controlling a staged rollout of the mobile daemon, and is not user-editable.)',
    (SELECT id FROM lu_config_field WHERE `field` = 'input'),
    @configType,
    'false',
    'true',
    'false',
    'settings',
    (SELECT COALESCE(MAX(c.sequence), 0) + 1 FROM config c WHERE c.type_id = @configType)
);
