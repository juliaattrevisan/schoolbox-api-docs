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
    0,
    'Enable Zenda',
    "If toggled to 'ON', parents will have access to the Zenda application.",
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
        0,
        'Enable Zenda Authentication',
        "If toggled to 'ON', parents will have access to the Zenda application.",
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
            SELECT COALESCE(MAX(sequence), 0) + 1
            FROM config c
            WHERE c.module = "zenda"
        )
    ),
    (
        'zenda_client_id',
        '',
        'Zenda Client ID',
        'Insert the Client ID for Zenda.',
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
            SELECT COALESCE(MAX(sequence), 0) + 1
            FROM config c
            WHERE c.module = "zenda"
        )
    ),
    (
        'zenda_client_secret',
        '',
        'Zenda Client Secret',
        'Insert the Client Secret for Zenda',
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
            SELECT COALESCE(MAX(sequence), 0) + 1
            FROM config c
            WHERE
            c.module = "zenda"
        )
    );
