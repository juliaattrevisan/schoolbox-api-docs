INSERT INTO
    config (
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
    )
VALUES (
        'pastoral_default_points_enabled',
        0,
        'Enable Default Pastoral Points',
        'If toggled to \'ON\', default points can be added to Types/Sub-Types. Once set, the default points value is automatically applied to records at creation and can only be overridden by Pastoral Care Moderators.',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                `field` = 'boolean'
        ),
        '',
        (
            SELECT id
            FROM lu_config_type
            WHERE
                `type` = 'Pastoral Care'
        ),
        'true',
        'false',
        'false',
        'settings',
        (
            SELECT MAX(sequence) + 1
            FROM config c
            WHERE
                `module` = 'settings'
        )
    )
