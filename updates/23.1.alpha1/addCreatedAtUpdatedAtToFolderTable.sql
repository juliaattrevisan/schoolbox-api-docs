ALTER TABLE folder ADD COLUMN created_at datetime NOT NULL DEFAULT current_timestamp() AFTER `parent`;
ALTER TABLE folder ADD COLUMN updated_at datetime NOT NULL DEFAULT current_timestamp() AFTER `created_at`;

ALTER TABLE folder ADD COLUMN created_by int(4) unsigned DEFAULT NULL;
ALTER TABLE folder ADD COLUMN updated_by int(4) unsigned DEFAULT NULL;

ALTER TABLE folder ADD CONSTRAINT fk_folder_created_by FOREIGN KEY (created_by) REFERENCES user (id);
ALTER TABLE folder ADD CONSTRAINT fk_folder_updated_by FOREIGN KEY (updated_by) REFERENCES user (id);
