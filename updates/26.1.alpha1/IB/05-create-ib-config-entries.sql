-- Add 'ib' to config module enum
ALTER TABLE `config`
MODIFY `module` ENUM(
    'settings',
    'comms',
    'custom',
    'oauth',
    'schoolData',
    'lti',
    'report-export',
    'feature-tours',
    'ai-services',
    'differentiation-profiles',
    'ib'
) DEFAULT 'settings';

-- Create IB config type
-- Hard-code the ID to match ConfigMapper::TYPE_INTERNATIONAL_BACCALAUREATE.
SET @ib_config_id = 26;
INSERT INTO
    `lu_config_type` (`id`, `type`, `sequence`)
VALUES (
        @ib_config_id,
        'International Baccalaureate',
        (
            SELECT MAX(sequence) + 1
            FROM (
                    SELECT sequence
                    FROM lu_config_type
                ) AS t
        )
    );

-- Create IB configuration entries in the config table
INSERT INTO
    `config` (
        `name`,
        `value`,
        `title`,
        `description`,
        `field_id`,
        `options`,
        `type_id`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        `sequence`
    )
VALUES (
        'ib_school_name',
        '',
        'School Name',
        'Enter the school\'s name as registered with the IB.',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'input'
        ),
        NULL,
        @ib_config_id,
        'true',
        'true',
        'false',
        'ib',
        1
    ),
    (
        'ib_school_code',
        '',
        'School Code',
        'Enter the school\'s code as provided by the IB.',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'input'
        ),
        NULL,
        @ib_config_id,
        'true',
        'true',
        'false',
        'ib',
        2
    );
