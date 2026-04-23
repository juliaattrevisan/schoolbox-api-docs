-- Create lu_ib_category and ib_system_purposes tables for IB System Purposes
CREATE TABLE `lu_ib_category`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_lu_ib_category_name` (`name`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lu_ib_category` (`name`)
VALUES ('DIPLOMA'),
       ('ANTICIPATED'),
       ('COURSE');

CREATE TABLE `ib_system_purposes`
(
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ib_system_purposes_name` (`name`),
    CONSTRAINT `fk_ib_system_purposes_category`
        FOREIGN KEY (`category_id`) REFERENCES `lu_ib_category` (`id`),
    KEY `idx_ib_system_purposes_category_id` (`category_id`),
    UNIQUE KEY `uniq_ib_system_purposes_name_category` (`name`, `category_id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ib_system_purposes` (`category_id`, `name`)
VALUES (1, 'Diploma Programme Year 2'),
       (2, 'Diploma Programme Year 1'),
       (3, 'Diploma Course');

-- Create lu_ibis_qualification table for IB Qualifications
-- This table will store qualification information as per SA-21623

CREATE TABLE `lu_qualification` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `system_purpose_id` INT(11) DEFAULT NULL,
    `sequence` int(11) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
    `deleted_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_sequence` (`sequence`),
    KEY `idx_system_purpose_id` (`system_purpose_id`),
    CONSTRAINT `fk_lu_qualification_system_purpose`
        FOREIGN KEY (`system_purpose_id`) REFERENCES `ib_system_purposes` (`id`)
            ON DELETE SET NULL
            ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Insert initial qualification data that was previously hardcoded
INSERT INTO `lu_qualification` (`name`, `system_purpose_id`, `sequence`) VALUES
('DP Year 1', 2, 1),
('DP Year 2', 1, 2),
('VCE', 3, 3);
