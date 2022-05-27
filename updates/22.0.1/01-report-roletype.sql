-- Choose a roletype for your report export
-- (apparently we use null for the lu_config_type...?)

INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('report_roletype',
        'student',
        'Role Type',
        'Choose the role types for report generation. Only users of this role type will get reports generated for them. ',
        'student;Student:staff;Staff',
        (SELECT id FROM lu_config_field WHERE field = 'selectKey'),
        null,
        'true',
        'false',
        'true',
        'report-export',
        (SELECT MAX(sequence) + 1
         FROM config c
         WHERE `module` = 'report-export'
         )
    );
