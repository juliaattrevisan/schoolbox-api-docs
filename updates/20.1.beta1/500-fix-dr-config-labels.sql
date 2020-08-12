-- Fix original copy/paste
UPDATE config
SET
    title       = 'Enable Disaster Recover feature',
    description = 'When enabled, Schoolbox files, config and data will be backed up to the Cloud',
    editable    = 'false',
    hidden      = 'true'
WHERE name = 'aws_s3_dr_backup_enabled';
