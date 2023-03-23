# replace the value `files` in backup_dir with `backups`
# i.e /var/lib/schoolbox/<instance_id>/files -> /var/lib/schoolbox/<instance_id>/backups
UPDATE config SET value = replace(value, 'files', 'backups') WHERE name = 'backup_dir';
UPDATE config SET value = replace(value, 'backup_dir:files', 'backup_dir:backups') WHERE name = 'data_dirs_defaults';
