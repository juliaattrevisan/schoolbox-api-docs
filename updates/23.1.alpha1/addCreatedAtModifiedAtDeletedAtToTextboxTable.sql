ALTER TABLE textBox ADD COLUMN created_at  datetime NOT NULL DEFAULT current_timestamp();
ALTER TABLE textBox ADD COLUMN modified_at datetime NOT NULL DEFAULT current_timestamp();
ALTER TABLE textBox CHANGE COLUMN deleted deleted_at datetime DEFAULT NULL AFTER modified_at;
