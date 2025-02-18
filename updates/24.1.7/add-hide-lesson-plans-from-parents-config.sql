-- Add new config into LMS Policy
SET
    @configType = (
        SELECT id
        FROM lu_config_type
        WHERE
            type = 'LMS Policy'
    );

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
        'restrict_parent_lesson_plan_access',
        'Hide Lesson Plans from Parents',
        0,
        'If toggled to ''ON'', parents will be prevented from being able to view Lesson Plans on a students calendar.',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                field = 'boolean'
        ),
        @configType,
        'true',
        'false',
        'false',
        'settings',
        (
            SELECT MAX(sequence) + 1
            FROM `config` c
            WHERE
                c.type_id = @configType
        )
    );

-- Shift the new setting to just below the restrict_parent_duework_access setting
UPDATE config c
INNER JOIN lu_config_type lct on c.type_id = lct.id
SET
    c.sequence = c.sequence + 1
WHERE
    lct.type = 'LMS Policy'
    AND c.sequence > (
        SELECT c2.sequence
        FROM config c2
        WHERE
            c2.name = 'restrict_parent_duework_access'
        LIMIT 1
    );

UPDATE `config`
SET
    `sequence` = (
        SELECT c2.sequence
        FROM config c2
        WHERE
            c2.name = 'restrict_parent_duework_access'
        LIMIT 1
    ) + 1
WHERE
    `name` = 'restrict_parent_lesson_plan_access';
