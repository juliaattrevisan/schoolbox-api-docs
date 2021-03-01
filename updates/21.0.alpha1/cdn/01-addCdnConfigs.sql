INSERT INTO config(
    name,
    value,
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
    'site_static_asset_serve_from_cdn',
    # Default to using CDN for prod: dev and staging need to turn this off
    0,
    'Serve static assets from CDN',
    'If this setting is on: static assets (JavaScript, CSS, non-uploaded images and files) will be served from a content delivery network, thus reducing bandwidth usage for on-premise instances.',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    '',
    (SELECT id FROM lu_config_type WHERE `type` = 'Resources'),
    # Probably not editable on could instances
    'true',
    'false',
    'false',
    'settings',
    (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Resources'))
);

INSERT INTO config(
    name,
    value,
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
    'site_static_asset_prefix',
    'https://s3-ap-southeast-2.amazonaws.com/schoolbox-static/{site_version}',
    'Static asset prefix',
    'If "Serve static assets from CDN" is on, static assets will be served from this URL. ({site_version} is replaced with the current Schoolbox version number.)',
    (SELECT id FROM lu_config_field WHERE field = 'input'),
    '',
    (SELECT id FROM lu_config_type WHERE `type` = 'Resources'),
    # Should not be editable, unless something changes
    'false',
    'true',
    'true',
    'settings',
    (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Resources'))
);
