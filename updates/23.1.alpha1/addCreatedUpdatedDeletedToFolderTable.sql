ALTER TABLE folder ADD COLUMN created_at datetime NOT NULL DEFAULT current_timestamp() AFTER `parent`;
ALTER TABLE folder ADD COLUMN updated_at datetime NOT NULL DEFAULT current_timestamp() AFTER `created_at`;

ALTER TABLE folder ADD COLUMN created_by int(4) unsigned DEFAULT NULL AFTER created_at;
ALTER TABLE folder ADD COLUMN updated_by int(4) unsigned DEFAULT NULL AFTER updated_at;
ALTER TABLE folder ADD COLUMN deleted_by int(4) unsigned DEFAULT NULL AFTER deleted_at;

ALTER TABLE folder ADD CONSTRAINT fk_folder_created_by FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE folder ADD CONSTRAINT fk_folder_updated_by FOREIGN KEY (updated_by) REFERENCES user (id);
ALTER TABLE folder ADD CONSTRAINT fk_folder_deleted_by FOREIGN KEY (deleted_by) REFERENCES user (id);

### seed folder table with historic data if it exists
update folder fd
    inner join cmsLog cmsLogCreate on (fd.id = cmsLogCreate.objectId and cmsLogCreate.object = 'folder' and cmsLogCreate.action = 'create')
    inner join cmsLog cmsLogModify on (fd.id = cmsLogModify.objectId and cmsLogModify.object = 'folder' and cmsLogModify.action = 'modify')
set fd.created_at = cmsLogCreate.time,
    fd.created_by = cmsLogCreate.user,
    fd.updated_at = cmsLogModify.time,
    fd.updated_by = cmsLogModify.user

WHERE fd.id > 0;
