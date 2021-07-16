UPDATE config
SET `sequence` = `sequence` + 1
WHERE type_id = (SELECT lct.id FROM lu_config_type lct WHERE lct.type = 'Policy')
  AND `sequence` > (SELECT `sequence` FROM config WHERE `name` = 'email_housemasters');
INSERT INTO config (`name`, `value`, `title`, `description`, `field_id`, `type_id`, `editable`, `hidden`, `required`,
                    `module`, `sequence`)
VALUES ('email_tutors',
        '0',
        'Allow Email Tutors from Class List',
        'If ticked, allows staff to email housemasters of a class with a single button',
        (SELECT lcf.id FROM lu_config_field lcf WHERE lcf.field = 'boolean'),
        (SELECT lct.id FROM lu_config_type lct WHERE lct.type = 'Policy'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT `sequence` + 1 FROM config cnf WHERE `name` = 'email_housemasters'));
