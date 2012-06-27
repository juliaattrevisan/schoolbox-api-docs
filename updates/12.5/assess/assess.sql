/* Allow Resit now becomes Max Attempts
 */
ALTER TABLE `assess` CHANGE `allow_resit` `max_attempts` INT(1) DEFAULT 0 NOT NULL;

/* Error message for making too many attempts
 */
INSERT INTO `error`(`id`,`message`) VALUES ( '146','You have reached the maximum number of attempts.');