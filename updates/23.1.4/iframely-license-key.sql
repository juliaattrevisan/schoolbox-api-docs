INSERT INTO
    config (
        name, value, title, description, field_id, type_id, editable, hidden, required, module
    )
VALUES (
        'iframely_license_key_private', '94aa8fc28ac1089a2dfb28', 'iFramely Private License Key', 'Private License key for iFramely for server-based communication', (
            SELECT id
            FROM lu_config_field
            WHERE
                `field` = "input"
        ), (
            SELECT id
            FROM lu_config_type
            WHERE
                `type` = "3rd Party Services"
        ), 'false', 'true', 'false', 'settings'
    );

INSERT INTO
    config (
        name, value, title, description, field_id, type_id, editable, hidden, required, module
    )
VALUES (
        'iframely_license_key_public', '1824167db8c94b042b966301b1e4ee0f', 'iFramely Public (Client) License Key', 'Public License key for iFramely for client-based communication', (
            SELECT id
            FROM lu_config_field
            WHERE
                `field` = "input"
        ), (
            SELECT id
            FROM lu_config_type
            WHERE
                `type` = "3rd Party Services"
        ), 'false', 'true', 'false', 'settings'
    );
