/* Ability for parents to see students' marks
 */
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'restrict_student_submissions','true','Restrict Student Submissions','If ticked, parents cannot see marks and details of their students\' submissions.','boolean','true:false','Security','true','false','false');

/* Fixed a typo for this config variable
 */
UPDATE `config` SET `name`='school_address' WHERE `configID`='3';