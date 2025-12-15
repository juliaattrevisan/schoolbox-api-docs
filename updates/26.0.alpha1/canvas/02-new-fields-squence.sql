SET
@canvas_config_id = (
        SELECT id
        FROM lu_config_type
        WHERE
            type = 'Canvas'
    );

### add new fields
INSERT INTO
config (
    `name`,
    `title`,
    `value`,
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
    'canvas_import_quiz',
    'Import Basic Quiz Data From Canvas',
    false,
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'boolean'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_quiz_worktype',
    'Imported Quiz Default Work Type',
    '',
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'input'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_import_grades',
    'Import Grades From Canvas',
    false,
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'boolean'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_grade_groups',
    'Schoolbox Grade Groups That Canvas letter Grades Are Mapped To',
    '',
    'TBA',
    (
        SELECT `id`
        FROM `lu_config_field`
        WHERE `field` = 'input'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_sissourceid_template',
    'SIS Source ID Template',
    '',
    'TBA',
    (
         SELECT `id`
         FROM `lu_config_field`
         WHERE `field` = 'input'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_sync_news',
    'Sync News Items From Canvas',
    false,
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'boolean'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_news_topic',
    'Schoolbox News Topic For Canvas News Items',
    '',
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'input'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_news_author',
    'Schoolbox User To Attribute As Author For Canvas News Items',
    '',
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'input'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_redirect_assessments',
    'Redirect to Canvas for Assessments',
    false,
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'boolean'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
), (
    'canvas_redirect_homepages',
    'Redirect to Canvas for Homepages',
    false,
    'TBA',
    (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'boolean'
    ),
    @canvas_config_id,
    'true',
    'false',
    'false',
    'settings',
    (
        SELECT MAX(sequence) + 1
        FROM `config` c
        WHERE
            c.module = 'settings'
            AND c.type_id = @canvas_config_id
    )
)
    ;

INSERT INTO config (`name`, `title`, `description`, `field_id`,  `type_id`, `options`, `editable`, `hidden`, `required`, `module`, sequence)
VALUES (
           'canvas_code_redirect_homepages',
           'Canvas Code to Redirect to Homepages',
           'TBA',
           (SELECT id FROM lu_config_field WHERE field = 'select'),
           @canvas_config_id,
           'course code:subject code:subject code from section',
           'true',
           'false',
           'true',
           'settings',
           (
               SELECT MAX(sequence) + 1
               FROM `config` c
               WHERE
                   c.module = 'settings'
                 AND c.type_id = @canvas_config_id
           )
       );