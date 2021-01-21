ALTER TABLE badge ADD COLUMN allow_set_as_goal tinyint(1) NOT NULL DEFAULT 1 AFTER image_path;
