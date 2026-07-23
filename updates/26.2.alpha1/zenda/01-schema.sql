-- Add a Zenda module option
alter table config
    modify module enum ('settings', 'comms', 'custom', 'oauth', 'schoolData', 'lti', 'report-export', 'feature-tours', 'ai-services', 'differentiation-profiles', 'ib', 'zenda') default 'settings' null;

-- Add a Zenda config type
INSERT INTO lu_config_type (type, sequence)
VALUES
    (
    'Zenda',
        (
            SELECT MAX(sequence) + 1
            FROM lu_config_type lct
        )
    );


-- Add a Zenda feature flag
INSERT INTO config (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
)
VALUES (
    'zenda_enabled',
    1,
    'Enable Zenda',
    "If toggled to 'ON', the Zenda integration will be enabled. If toggled to 'OFF', it will be disabled for all users.",
    (
        SELECT id
        FROM lu_config_field
        WHERE field = 'boolean'
    ),
    (
        SELECT id
        FROM lu_config_type
        WHERE type = 'Modules'
    ),
    'true',
    'true',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM config c
        WHERE c.module = "settings"
    )
);

-- Add Zenda client id and client secret; there might be more after this
INSERT INTO
    config (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
)
VALUES
    (
        'zenda_active',
        1,
        'Activate Zenda',
        '',
        (
            SELECT id
            FROM lu_config_field
            WHERE field = 'boolean'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE type = 'Zenda'
        ),
        'true',
        'false',
        'false',
        'zenda',
        (
            SELECT MAX(sequence) + 1
            FROM config c
            WHERE c.module = "zenda"
        )
    ),
    (
        'zenda_client_id',
        '',
        'Zenda Client ID',
        '',
        (
            SELECT id
            FROM lu_config_field
            WHERE field = 'input'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE type = 'Zenda'
        ),
        'true',
        'false',
        'false',
        'zenda',
        (
            SELECT MAX(sequence) + 1
            FROM config c
            WHERE c.module = "zenda"
        )
    ),
    (
        'zenda_client_secret',
        '',
        'Zenda Client Secret',
        '',
        (
            SELECT id
            FROM lu_config_field
            WHERE
            field = 'password'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
            type = 'Zenda'
        ),
        'true',
        'false',
        'false',
        'zenda',
        (
            SELECT MAX(sequence) + 1
            FROM config c
            WHERE
            c.module = "zenda"
        )
    );
