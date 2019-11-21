INSERT INTO `config` (`name`, `value`, `title`, `description`, `field_id`, `options`, `type_id`, `editable`, `hidden`,`required`)
VALUES (
            'strict_templates',
            '0',
            'Enable Strict Templates',
            'Let developers to enable strict mode in Twig to test for bugs and issues.',
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
