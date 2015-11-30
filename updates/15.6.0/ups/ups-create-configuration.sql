-- UPS configuration table
CREATE TABLE `ups_config` (
    `role_type` ENUM('student','parent','staff') NOT NULL,
    `enabled` BOOL NOT NULL DEFAULT 1,
    PRIMARY KEY (`role_type`)
);
