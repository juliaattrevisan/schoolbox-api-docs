INSERT INTO
    config (
        name,
        value,
        title,
        description,
        field_id,
        type_id,
        editable,
        hidden,
        required,
        module
    )
VALUES (
        'ckeditor5_license_key',
        'M1JNczQvV3BZWTlrSytBMU9POVdkWVZ3eU1GMzVmbHhTdVI2a2xuM0JXdUQ5RFlHMkM3UVNHWktLcXhWc1ltb1BGTGczMTg9LU1qQXlOVEV4TURjPQ==',
        'CKEditor5 License Key',
        'License key for CKEditor5 to enable premium features',
        (
            SELECT id
            FROM lu_config_field
            WHERE
                `field` = "input"
        ),
        (
            SELECT id
            FROM lu_config_type
            WHERE
                `type` = "3rd Party Services"
        ),
        'false',
        'true',
        'false',
        'settings'
    );

UPDATE config
SET
    value = 'M1JNczQvV3BZWTlrSytBMU9POVdkWVZ3eU1GMzVmbHhTdVI2a2xuM0JXdUQ5RFlHMkM3UVNHWktLcXhWc1ltb1BGTGczMTg9LU1qQXlOVEV4TURjPQ=='
WHERE
    name = 'ckeditor5_license_key';
