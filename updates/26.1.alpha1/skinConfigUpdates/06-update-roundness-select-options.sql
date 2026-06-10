UPDATE config
SET
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'select',
            JSON_ARRAY(
                JSON_OBJECT(
                    'name',
                    'None',
                    'value',
                    'var(--sbx-border-radius-none)'
                ),
                JSON_OBJECT(
                    'name',
                    'Small',
                    'value',
                    'var(--sbx-border-radius-s)'
                ),
                JSON_OBJECT(
                    'name',
                    'Medium',
                    'value',
                    'var(--sbx-border-radius-m)'
                ),
                JSON_OBJECT(
                    'name',
                    'Large',
                    'value',
                    'var(--sbx-border-radius-l)'
                )
            )
        )
    )
WHERE
    name IN (
        'skin_field_corner_radius',
        'skin_container_corner_radius'
    );
