ALTER TABLE textBox ADD COLUMN created_at datetime NOT NULL DEFAULT current_timestamp();
ALTER TABLE textBox ADD COLUMN updated_at datetime NOT NULL DEFAULT current_timestamp();
ALTER TABLE textBox CHANGE COLUMN deleted deleted_at datetime DEFAULT NULL AFTER updated_at;

ALTER TABLE textBox ADD COLUMN created_by int(4) unsigned DEFAULT NULL AFTER created_at;
ALTER TABLE textBox ADD COLUMN updated_by int(4) unsigned DEFAULT NULL AFTER updated_at;
ALTER TABLE textBox ADD COLUMN deleted_by int(4) unsigned DEFAULT NULL AFTER deleted_at;

ALTER TABLE textBox ADD CONSTRAINT fk_textBox_created_by FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE textBox ADD CONSTRAINT fk_textBox_updated_by FOREIGN KEY (updated_by) REFERENCES user (id);
ALTER TABLE textBox ADD CONSTRAINT fk_textBox_deleted_by FOREIGN KEY (deleted_by) REFERENCES user (id);


### seed textbox table with historic data if it exists
update textBox tb
    left join cmsLog cmsLogCreate on (tb.textBoxID = cmsLogCreate.objectId and cmsLogCreate.object = 'content' and cmsLogCreate.action = 'create')
    left join cmsLog cmsLogModify on (tb.textBoxID = cmsLogModify.objectId and cmsLogModify.object = 'content' and cmsLogModify.action = 'modify')
    left join cmsLog cmsLogDelete on (tb.textBoxID = cmsLogDelete.objectId and cmsLogDelete.object = 'content' and cmsLogDelete.action = 'delete')
set
    tb.created_at = COALESCE(cmsLogCreate.time, tb.created_at),
    tb.created_by = cmsLogCreate.user,
    tb.updated_at = COALESCE(cmsLogModify.time, tb.deleted_at, cmsLogCreate.time, tb.updated_at),
    tb.updated_by = coalesce(cmsLogModify.user, cmsLogCreate.user),
    tb.deleted_by = cmsLogDelete.user

WHERE tb.textBoxID > 0;
