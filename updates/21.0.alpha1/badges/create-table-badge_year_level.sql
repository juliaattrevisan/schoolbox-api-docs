CREATE TABLE badge_year_level
(
    badge_id      INT UNSIGNED NOT NULL,
    year_level_id INT UNSIGNED NOT NULL,
    CONSTRAINT `fk_badge_year_level_badge_id` FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_badge_year_level_year_level_id` FOREIGN KEY (year_level_id) REFERENCES lu_year_level (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
