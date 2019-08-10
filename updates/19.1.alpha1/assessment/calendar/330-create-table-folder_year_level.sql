CREATE TABLE folder_year_level (
    folder_id INT UNSIGNED NOT NULL,
    year_level_id INT UNSIGNED NOT NULL,
    sequence INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT `fk_folder_year_level_folder_id` FOREIGN KEY (folder_id) REFERENCES folder (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_folder_year_level_year_level_id` FOREIGN KEY (year_level_id) REFERENCES lu_year_level (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

