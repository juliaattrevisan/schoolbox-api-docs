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
                JSON_OBJECT('name', 'None', 'value', '0'),
                JSON_OBJECT(
                    'name',
                    'Small',
                    'value',
                    '0.25rem'
                ),
                JSON_OBJECT(
                    'name',
                    'Medium',
                    'value',
                    '0.5rem'
                ),
                JSON_OBJECT(
                    'name',
                    'Large',
                    'value',
                    '0.75rem'
                )
            )
        )
    )
WHERE
    name IN (
        'skin_field_roundness',
        'skin_box_roundness'
    );
