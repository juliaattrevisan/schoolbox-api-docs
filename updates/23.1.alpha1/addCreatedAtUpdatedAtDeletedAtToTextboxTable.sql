ALTER TABLE textBox ADD COLUMN created_at datetime NOT NULL DEFAULT current_timestamp();
ALTER TABLE textBox ADD COLUMN updated_at datetime NOT NULL DEFAULT current_timestamp();
ALTER TABLE textBox CHANGE COLUMN deleted deleted_at datetime DEFAULT NULL AFTER updated_at;

ALTER TABLE textBox ADD COLUMN created_by int(4) unsigned DEFAULT NULL AFTER created_at;
ALTER TABLE textBox ADD COLUMN updated_by int(4) unsigned DEFAULT NULL AFTER updated_at;
ALTER TABLE textBox ADD COLUMN deleted_by int(4) unsigned DEFAULT NULL AFTER deleted_at;

ALTER TABLE textBox ADD CONSTRAINT fk_textBox_created_by FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE textBox ADD CONSTRAINT fk_textBox_updated_by FOREIGN KEY (updated_by) REFERENCES user (id);
ALTER TABLE textBox ADD CONSTRAINT fk_textBox_deleted_by FOREIGN KEY (deleted_by) REFERENCES user (id);
