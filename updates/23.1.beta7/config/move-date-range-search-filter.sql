# Move from LMS > LMS Policy to System Settings > Policy

UPDATE config
SET `type_id` = (
        SELECT `id`
        FROM `lu_config_type`
        WHERE
            `type` = 'Policy'
    ),
    `sequence` = (
        SELECT
            max(sequence) + 1
        FROM `config` c
        WHERE type_id = (
                SELECT `id`
                FROM
                    `lu_config_type`
                WHERE
                    `type` = 'Policy'
            )
    )
WHERE
    name = 'date_range_search_filter';
