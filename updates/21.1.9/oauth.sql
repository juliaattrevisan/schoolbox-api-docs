INSERT INTO lu_config_field (id, field)
VALUES (19, 'uri');


UPDATE config SET field_id = (SELECT id FROM lu_config_field WHERE field = 'uri')
WHERE name = 'cloudwork_oauth_identity_provider';

UPDATE config SET field_id = (SELECT id FROM lu_config_field WHERE field = 'uri')
WHERE name in ('cloudwork_oauth_identity_provider',

    'school_address',
    'external_portal',
    'external_staff_portal',
    'external_tass_sso_server',
    'cyberhound_api_url',
    'saml_idp_metadata_url',
    'saml_slo_url',
    'saml_sso_url'
    );
