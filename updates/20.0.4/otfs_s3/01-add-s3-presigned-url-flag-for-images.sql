INSERT INTO config (
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
) VALUES (
    'otfs_image_s3_presigned_urls_enabled',
    '0',
    'Enable AWS S3 PreSigned URLs Images',
    'If enabled, files stored in S3 will be served directly from S3 to clients for all images, without downl via Schoolbox',
    (SELECT id FROM lu_config_field WHERE field = 'boolean'),
    (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
    'false',
    'false',
    'true',
    'settings'
);
