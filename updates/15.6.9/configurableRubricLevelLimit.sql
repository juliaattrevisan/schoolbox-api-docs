REPLACE INTO `config` (
    `name`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `value`,
    `editable`
) VALUES (
    'rubric_max_levels',
    'Maximum Rubric Levels',
    'The maximum number of rubric levels. (If you use predefined rubric result sets, this number must be at least the number of results in your largest set otherwise they will not all import.)',
    1,
    15,
    GREATEST(
        11,
        (SELECT COUNT(resultset_id) FROM rubric_result GROUP BY resultset_id ORDER BY COUNT(resultset_id) DESC LIMIT 1)
    ),
    'true'
);
