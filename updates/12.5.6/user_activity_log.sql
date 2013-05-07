-- User activity log table. Would like feedback on which fields are best indexed.
CREATE TABLE user_activity_log (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `date` datetime NOT NULL,
    `ip` varchar(15) NOT NULL,
    `event` varchar(20) NOT NULL,
    `message` varchar(250) NOT NULL,
    `level` enum('error', 'warn', 'info', 'debug', 'trace') NOT NULL DEFAULT 'info',
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
ALTER TABLE `user_activity_log` ADD INDEX `username` (`username` ASC) ;
ALTER TABLE `user_activity_log` ADD INDEX `date` (`date` DESC) ;
