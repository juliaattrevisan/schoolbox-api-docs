-- Adds a Compact Mode component config to My Students dashboard component

INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES (
        (
            SELECT id
            FROM component_definition
            WHERE `class` = 'Component_Dashboard_ChildSubjectController' LIMIT 1
        ),
        'compactMode',
        (
            SELECT
            CASE WHEN (
                SELECT value
                FROM config
                WHERE config.name = 'site_school_code'
            ) IN ('vagrant') THEN 1 ELSE 0 END)
        )
        'Compact Mode',
        null
);
