INSERT INTO
    config (
        `name`,
        `value`,
        `title`,
        `description`,
        `field_id`,
        `type_id`,
        `options`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        sequence
    )
VALUES (
        'editor_templates_badge_evidence_enabled',
        '1',
        'Badge Evidence Editor Templates',
        'When enabled badge evidence editor templates will be available in Admin/Design/Editor Templates',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'boolean'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                type = 'Site'
        ),
        NULL,
        'false',
        'true',
        'true',
        'settings',
        NULL
    );

INSERT INTO
    config (
        `name`,
        `value`,
        `title`,
        `description`,
        `field_id`,
        `type_id`,
        `options`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        sequence
    )
VALUES (
        'editor_templates_feedback_enabled',
        '1',
        'Feedback Editor Templates',
        'When enabled feedback editor templates will be available in Admin/Design/Editor Templates',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'boolean'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                type = 'Site'
        ),
        NULL,
        'false',
        'true',
        'true',
        'settings',
        NULL
    );

INSERT INTO
    config (
        `name`,
        `value`,
        `title`,
        `description`,
        `field_id`,
        `type_id`,
        `options`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        sequence
    )
VALUES (
        'editor_templates_learning_activity_enabled',
        '1',
        'Learning Activity Editor Templates',
        'When enabled learning activity editor templates will be available in Admin/Design/Editor Templates',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'boolean'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                type = 'Site'
        ),
        NULL,
        'false',
        'true',
        'true',
        'settings',
        NULL
    );

INSERT INTO
    config (
        `name`,
        `value`,
        `title`,
        `description`,
        `field_id`,
        `type_id`,
        `options`,
        `editable`,
        `hidden`,
        `required`,
        `module`,
        sequence
    )
VALUES (
        'editor_templates_learning_moments_enabled',
        '1',
        'Learning Moments Editor Templates',
        'When enabled learning moments editor templates will be available in Admin/Design/Editor Templates',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'boolean'
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                type = 'Site'
        ),
        NULL,
        'false',
        'true',
        'true',
        'settings',
        NULL
    );
