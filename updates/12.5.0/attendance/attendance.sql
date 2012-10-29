/* Error messages
 */
INSERT INTO error (id, message) VALUES
(147, 'Please specify a class code, date and period.'),
(148, 'This class does not exist.'),
(149, 'Please specify a date in the format: yyyy-mm-dd'),
(150, 'Please specify a valid period number.'),
(151, 'You cannot edit attendance records this far in the past.'),
(152, 'You cannot edit attendance records in the future.');


INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'external_attendance','0','Enable Online Attendance','Allows teachers to mark attendance online, only compatible with Synergetic.','boolean',NULL,'External DB','true','false','false');
