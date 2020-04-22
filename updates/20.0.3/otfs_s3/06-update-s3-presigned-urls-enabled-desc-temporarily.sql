-- Temporarily update the description for otfs_s3_presigned_urls_enabled flag

UPDATE `config` SET `description` = 'If enabled, files stored in S3 will be served directly from S3 to clients, without streaming via Schoolbox (Currently being used by send, sendVideo and fetch)'
WHERE (`name` = 'otfs_s3_presigned_urls_enabled');
