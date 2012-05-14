/* Config variable which prevents staff members from posting news items
 */
INSERT INTO `config`(`configID`,`name`,`value`,`title`,`description`,`field`,`options`,`type`,`editable`,`hidden`,`required`) VALUES ( NULL,'news_staff_post_enabled','0','Allow Staff to create News','If ticked, staff members can create news posts.','boolean','true:false','Resources','true','false','false');
