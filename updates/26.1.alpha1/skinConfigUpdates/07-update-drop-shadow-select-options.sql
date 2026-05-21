SET
    @config_select_field_id = (
        SELECT id
        FROM lu_config_field
        WHERE
            field = 'select'
        LIMIT 1
    );

UPDATE config
SET
    field_id = @config_select_field_id,
    `value` = CASE
        WHEN `value` IN (
            'var(--sbx-shadow-none)',
            'var(--sbx-shadow-s)',
            'var(--sbx-shadow-m)',
            'var(--sbx-shadow-l)'
        ) THEN `value`
        ELSE 'var(--sbx-shadow-none)'
    END,
    options = JSON_MERGE_PATCH(
        CASE
            WHEN options IS NULL
            OR TRIM(options) = ''
            OR JSON_VALID(options) = 0 THEN JSON_OBJECT()
            ELSE options
        END,
        JSON_OBJECT(
            'customProperty',
            '--sbx-config-container-drop-shadow',
            'select',
            JSON_ARRAY(
                JSON_OBJECT(
                    'name',
                    'None',
                    'value',
                    'var(--sbx-shadow-none)'
                ),
                JSON_OBJECT(
                    'name',
                    'Small',
                    'value',
                    'var(--sbx-shadow-s)'
                ),
                JSON_OBJECT(
                    'name',
                    'Medium',
                    'value',
                    'var(--sbx-shadow-m)'
                ),
                JSON_OBJECT(
                    'name',
                    'Large',
                    'value',
                    'var(--sbx-shadow-l)'
                )
            )
        )
    )
WHERE
    name = 'skin_box_drop_shadow';
