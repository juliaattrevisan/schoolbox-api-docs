CREATE TABLE `user_password_reset_request`(
    `token` VARCHAR(64) CHARSET latin1 NOT NULL,
    `user_id` INT NOT NULL,
    `expiry` DATETIME NOT NULL,
    PRIMARY KEY (`token`)
);
