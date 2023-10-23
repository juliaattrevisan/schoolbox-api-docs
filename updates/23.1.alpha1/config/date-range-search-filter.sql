INSERT INTO
    `config`(
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
        `sequence`
    )
VALUES (
        'date_range_search_filter',
        '0',
        'Date range for search filter',
        'The default date range that should be applied on the search page', (
            SELECT `id`
            FROM
                `lu_config_field`
            WHERE
                `field` = 'selectKey'
        ), (
            SELECT `id`
            FROM
                `lu_config_type`
            WHERE
                `type` = 'Localisation'
        ),
        '0;School year:1;1 year (6 months in the past, 6 months in the future):2;2 years (1 year in the past, 1 year in the future):3;5 years (2.5 years in the past, 2.5 years in the future)',
        'true',
        'false',
        'true',
        'settings', (
            SELECT
                max(sequence) + 1
            FROM `config` c
            WHERE type_id = (
                    SELECT
                        `id`
                    FROM
                        `lu_config_type`
                    WHERE
                        `type` = 'Localisation'
                )
        )
    );
