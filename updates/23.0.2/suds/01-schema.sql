-- arbitrary table for persistent logging in cloud clusters

CREATE TABLE `log` (
`id` INT NOT NULL AUTO_INCREMENT,
`data` LONGTEXT NULL,
`created_at` DATETIME NOT NULL,
PRIMARY KEY (`id`));
