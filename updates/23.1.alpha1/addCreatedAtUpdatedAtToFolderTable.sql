ALTER TABLE folder ADD COLUMN created_at datetime NOT NULL DEFAULT current_timestamp() AFTER `parent`;
ALTER TABLE folder ADD COLUMN updated_at datetime NOT NULL DEFAULT current_timestamp() AFTER `created_at`;
