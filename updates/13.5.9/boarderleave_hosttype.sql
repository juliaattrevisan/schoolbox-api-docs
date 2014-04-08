/* Convert $hostType to a lookup
 */
CREATE TABLE `lu_leave_request_host_type`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `lu_leave_request_host_type` (`name`) VALUES ('Mother');
INSERT INTO `lu_leave_request_host_type` (`name`) VALUES ('Father');
INSERT INTO `lu_leave_request_host_type` (`name`) VALUES ('Guardian');
INSERT INTO `lu_leave_request_host_type` (`name`) VALUES ('Self');
INSERT INTO `lu_leave_request_host_type` (`name`) VALUES ('Other');