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
    left join cmsLog cmsLogCreate on (fd.id = cmsLogCreate.objectId and cmsLogCreate.object = 'folder' and cmsLogCreate.action = 'create')
set fd.created_at = COALESCE(cmsLogCreate.time, fd.created_at),
    fd.created_by = cmsLogCreate.user
WHERE fd.id > 0;

update folder fd
    left join cmsLog cmsLogDelete on (fd.id = cmsLogDelete.objectId and cmsLogDelete.object = 'folder' and cmsLogDelete.action = 'delete')
set fd.deleted_by = cmsLogDelete.user
WHERE fd.id > 0;

update folder fd
    left join cmsLog cmsLogModify on (fd.id = cmsLogModify.objectId and cmsLogModify.object = 'folder' and cmsLogModify.action = 'modify')
set fd.updated_at = COALESCE(cmsLogModify.time, fd.deleted_at, fd.created_at),
    fd.updated_by = coalesce(cmsLogModify.user, fd.deleted_by, fd.created_by)
WHERE fd.id > 0;
