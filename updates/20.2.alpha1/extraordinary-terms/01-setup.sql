-- Create relational table

CREATE TABLE term_year_level (
    term_id     INT NOT NULL,
    year_level_id INT UNSIGNED NOT NULL,
CONSTRAINT `fk_term_year_level_term_id` FOREIGN KEY (term_id) REFERENCES term (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
CONSTRAINT `fk_term_year_level_year_level_id` FOREIGN KEY (year_level_id) REFERENCES lu_year_level (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

