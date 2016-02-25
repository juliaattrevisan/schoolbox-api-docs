-- IPv6 address: 45 chars.
-- https://stackoverflow.com/questions/166132/maximum-length-of-the-textual-representation-of-an-ipv6-address
CREATE TABLE `user_password_reset_request`(
    `token` VARCHAR(64) CHARSET latin1 NOT NULL,
    `user_id` INT NOT NULL,
    `expiry` DATETIME NOT NULL,
    `ip` VARCHAR(45) CHARSET latin1 NOT NULL DEFAULT '',
    `reset_time` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`token`)
);
