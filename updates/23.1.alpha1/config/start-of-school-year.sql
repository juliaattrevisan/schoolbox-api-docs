INSERT INTO
    `config`(
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
        'start_of_school_year',
        '01/01',
        'Start of School Year',
        'Add the first date of the school''s academic year for filtering data in d/m format. This will apply across multiple academic years, so the date should only include a day and a month.', (
            SELECT `id`
            FROM
                `lu_config_field`
            WHERE
                `field` = 'input'
        ), (
            SELECT `id`
            FROM
                `lu_config_type`
            WHERE
                `type` = 'Localisation'
        ),
        'true',
        'false',
        'true',
        'settings',
        0
    );

UPDATE `config`
SET
    `options` = '0;Forever:1;12 Months:2;6 Months:3;Current Calendar Year:4;Current Academic Period:5;Current School Year'
WHERE
    `name` = 'pastoral_flag_count_period';

UPDATE `config`
SET `sequence` = sequence + 1
WHERE `type_id` = (
        SELECT `id`
        FROM `lu_config_type`
        WHERE
            `type` = 'Localisation'
    );
