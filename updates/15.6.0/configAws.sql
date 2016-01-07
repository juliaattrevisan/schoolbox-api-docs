INSERT INTO config (
    `name`, `value`, title, description, field_id, type_id, editable, hidden, required
) VALUES (
    'otfs_s3_cred_key', '', 'AWS S3 Key',  'AWS S3 Key',
    '1', '10', 'true', 'false', 'false'
), (
    'otfs_s3_cred_secret', '', 'AWS S3 Secret',  'AWS S3 Secret',
    '1', '10', 'true', 'false', 'false'
), (
    'otfs_s3_region', '', 'AWS S3 Region', 'AWS S3 Region (eg. ap-southeast-1)',
    '1', '10', 'true', 'false', 'false'
), (
    'otfs_s3_version', '', 'AWS S3 Version', 'AWS S3 Version (eg. 2/3/latest)',
    '1', '10', 'true', 'false', 'false'
), (
    'otfs_s3_bucket', '', 'AWS S3 Bucket', 'AWS S3 Bucket',
    '1', '10', 'true', 'false', 'false'
), (
    'otfs_s3_prefix', '', 'AWS S3 Prefix',   'AWS S3 Prefix',
    '1', '10', 'true', 'false', 'false'
) ON DUPLICATE KEY UPDATE `name` = `name`;

UPDATE config SET `value` = 'latest' WHERE `name` = 'otfs_s3_version';