/* Update to survey permissions
 */
INSERT INTO `error`(`id`,`message`) VALUES ( '142','You do not have permissions to delete this attempt(s).');
UPDATE `error` SET `message`='You do not have permissions to create and modify test contents.' WHERE `id`='130';
UPDATE `error` SET `message`='You do not have permissions to create and modify tests.' WHERE `id`='126';