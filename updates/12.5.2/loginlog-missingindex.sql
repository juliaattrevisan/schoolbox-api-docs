/* add missing index to improve performance of activity log queries [SB-1774]*/
ALTER TABLE `login_log` ADD INDEX `username` (`username` ASC) ; 
/* add missing index to improve performance of group statistics queries [SB-1778]*/
ALTER TABLE `login_log` ADD INDEX `date` (`date` DESC) ;
