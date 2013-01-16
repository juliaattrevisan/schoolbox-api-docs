/* add missing index to improve performance of activity log queries [SB-1774]*/
ALTER TABLE `login_log` ADD INDEX `username` (`username` ASC) ; 
