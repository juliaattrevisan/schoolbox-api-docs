INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,`required`)
VALUES (
            'strict_templates',
            '0',
            'Enable Strict Templates',
            'This will cause Twig to display errors if variables are accessed that are undefined',
            (
                SELECT id
                FROM lu_config_field
                WHERE field = 'boolean'
            ),
            NULL,
            (
                SELECT id
                FROM lu_config_type
                WHERE type = 'Debugging'
            ),
            'true',
            'false',
            'false'
        );
