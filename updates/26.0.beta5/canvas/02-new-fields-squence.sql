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
    'If toggled to ''ON'', the system will attempt to import quiz data from Canvas.',
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
    'The Schoolbox work type attributed to any imported quizzes from Canvas.',
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
    'If toggled to ''ON'', the system will attempt to import grades from Canvas.',
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
    'Schoolbox Grade Group That Canvas Letter Grades Are Mapped To',
    '',
    'The Schoolbox grade group used for mapping Canvas grades.',
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
    '{folderCode}{parentCode}',
    'Template Schoolbox uses to map Canvas data to Schoolbox data. Supports {folderCode}, {parentCode}, {folderName}, and {year}.',
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
    'Sync News',
    false,
    'If toggled to ''ON'', the system will attempt to import news from Canvas.',
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
    'Schoolbox News Topic',
    '',
    'The Schoolbox news topic attributed to any imported news from Canvas.',
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
    'Schoolbox News Owner',
    '',
    'The Schoolbox user attributed to any imported news from Canvas.',
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
    'Redirect from Assessments',
    false,
    'If toggled to ''ON'', the system will redirect users to the Canvas assessments when accessing the Schoolbox learning activity.',
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
    'Redirect from Homepages',
    false,
    'If toggled to ''ON'', the system will redirect users to the Canvas page when accessing the Schoolbox homepage.',
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
           'Redirect homepage to',
           'The Canvas object used for redirection.',
           (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
           @canvas_config_id,
           'course_code;course code:subject_code;subject code:subject_code_section;subject code from section',
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
       );
