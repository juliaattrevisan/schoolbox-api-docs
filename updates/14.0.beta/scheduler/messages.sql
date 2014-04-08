CREATE TABLE `msg_namespace`(
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`  VARCHAR(32) NOT NULL,
  `label` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`slug`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_source`(
  `id`            INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `namespace`     INT(10) NOT NULL,
  `key`           VARCHAR(64) NOT NULL,
  `role_type`     ENUM('student','parent','staff') NOT NULL,
  `description`   VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`namespace`, `key`, `role_type`),
  INDEX (`namespace`, `key`),
  INDEX (`key`),
  INDEX (`namespace`),
  INDEX (`role_type`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_reroute`(
  `user_id`        INT UNSIGNED NOT NULL,
  `msg_source_id`  INT UNSIGNED NOT NULL,
  `outgoing_level` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `msg_source_id`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_route_perm`(
  `msg_source_id` INT UNSIGNED NOT NULL,
  `role_id`       INT UNSIGNED NOT NULL,
  `min_level`     INT UNSIGNED NOT NULL,
  `max_level`     INT UNSIGNED NOT NULL,
  `default_level` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`msg_source_id`, `role_id`),
  INDEX (`role_id`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_reroute_override`(
  `user_id` INT(10) UNSIGNED NOT NULL,
  `msg_route_key` VARCHAR(64) NOT NULL,
  `outgoing_level` INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`, `msg_route_key`),
  INDEX (`user_id`)
) CHARSET=utf8 COLLATE=utf8_bin;

/*Data for the table `msg_namespace` */

INSERT INTO `msg_namespace`(`id`,`slug`,`label`) VALUES
  (1,'duework','Due Work'),
  (2,'assess','Online Assessment'),
  (3,'blog','Blog'),
  (4,'forum','Forum'),
  (5,'news','News'),
  (6,'calendar','Calendar'),
  (7,'leave','Boarder Leave'),
  (8,'wiki','Wiki');


/* Data for `msg_source` */
INSERT INTO `msg_source` (`id`,`namespace`,`key`,`role_type`,`description`) VALUES
  (1,1,'open','student','a submission box that you need to submit to opens'),
  (2,1,'upcoming','student','you have outstanding work that is due soon'),
  (3,1,'updated','student','a submission box is updated'),
  (4,1,'overdue','student','you have outstanding work that is overdue'),
  (5,1,'closed','staff','a submission box has closed with submissions outstanding'),
  (6,1,'submitted','staff','a student submits work'),
  (7,1,'returned','student','a teacher marks your work'),
  (8,1,'returned','parent','a teacher marks your child\'s work'),
  (9,1,'overdue','parent','your child has outstanding work that is overdue'),
  (10,1,'upcoming','parent','your child has outstanding work that is due soon'),
  (11,2,'open','student','an online assessment that you need to attempt opens'),
  (12,2,'upcoming','student','you have yet to attempt an assessment that is due soon'),
  (13,2,'upcoming','parent','your child has yet to attempt an assessment that is due soon'),
  (14,2,'updated','student','an assessment is updated'),
  (15,2,'overdue','student','you failed to attempt an assessment'),
  (16,2,'overdue','parent','your child failed to attempt an assessment'),
  (17,2,'closed','staff','an assessment you posted has closed'),
  (18,2,'attempted','staff','a student attempts an assessment you posted'),
  (19,2,'marked','student','a teacher marks your attempt of an assessment'),
  (20,2,'marked','parent','a teacher marks your child\'s attempt of an assessment'),
  (21,3,'commented','student','someone comments on a blog post you have written'),
  (22,3,'followed','student','someone comments after you on a blog post'),
  (23,3,'followed','staff','someone comments after you on a blog post'),
  (24,3,'commented','staff','someone comments on a blog post you have written'),
  (25,4,'followed','student','someone comments after you on a forum'),
  (26,4,'followed','staff','someone comments after you on a forum'),
  (27,4,'mentioned','staff','someone mentions you on a forum'),
  (28,4,'mentioned','student','someone mentions you on a forum'),
  (29,5,'posted','student','someone posts school news'),
  (30,5,'posted','parent','someone posts school news'),
  (31,5,'posted','staff','someone posts school news'),
  (32,6,'upcoming','student','an event on your calendar is about to start'),
  (33,6,'upcoming','staff','an event on your calendar is about to start'),
  (34,6,'upcoming','parent','an event on your child\'s calendar is about to start'),
  (35,6,'updated','student','an event on your calendar is updated'),
  (36,6,'updated','staff','an event on your calendar is updated'),
  (37,6,'updated','parent','an event on your child\'s calendar is updated'),
  (38,7,'student_requested','staff','a student requests boarder leave'),
  (39,7,'parent_requested','staff','a parent requests boarder leave for their child'),
  (40,7,'approved','student','your boarder leave is approved'),
  (41,7,'approved','parent','your child\'s boarder leave is approved'),
  (42,7,'rejected','student','your boarder leave is rejected'),
  (43,7,'rejected','parent','your child\'s boarder leave is rejected'),
  (44,1,'datechanged','student','the due date of a submission box is changed'),
  (45,1,'datechanged','parent','the due date of a submission box is changed'),
  (46,1,'renamed','student','a submission box is renamed'),
  (47,1,'renamed','parent','a submission box is renamed'),
  (48,1,'renamed','staff','a submission box is renamed');
