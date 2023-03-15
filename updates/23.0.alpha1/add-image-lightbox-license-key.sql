INSERT INTO
    config (
        name,
        `value`,
        title,
        description,
        field_id,
        options,
        type_id,
        editable,
        hidden,
        required,
        module,
        sequence
    )
VALUES (
        'image_lightbox_license_key',
        'B3EFE861-7F3F-43AA-9B2C-98540F09D470',
        'Image Lightbox License Key',
        'Image Lightbox License Key', (
            SELECT id
            FROM
                lu_config_field
            WHERE
                `field` = 'input'
        ),
        NULL, (
            SELECT id
            FROM
                lu_config_type
            WHERE
                `type` = '3rd Party Services'
        ),
        'false',
        'true',
        'true',
        'settings',
        NULL
    );
