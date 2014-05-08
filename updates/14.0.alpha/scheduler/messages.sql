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
  (8,'wiki','Wiki'),
  /* 9 is reserved for EOTC */
  (10, 'group', 'Groups');


/* Data for `msg_source` */
INSERT INTO `msg_source` (`id`,`namespace`,`key`,`role_type`,`description`) VALUES
  (NULL,1,'open','student','a submission box that you need to submit to opens'),
  (NULL,1,'upcoming','student','you have outstanding work that is due soon'),
  (NULL,1,'updated','student','a submission box is updated'),
  (NULL,1,'overdue','student','you have outstanding work that is overdue'),
  (NULL,1,'returned','student','a teacher marks your work'),
  (NULL,1,'datechanged','student','the due date of a submission box is changed'),
  (NULL,1,'renamed','student','a submission box is renamed'),
  (NULL,1,'renamed','staff','a submission box is renamed'),
  (NULL,1,'closed','staff','a submission box has closed with submissions outstanding'),
  (NULL,1,'submitted','staff','a student submits work'),
  (NULL,1,'returned','parent','a teacher marks your child\'s work'),
  (NULL,1,'overdue','parent','your child has outstanding work that is overdue'),
  (NULL,1,'upcoming','parent','your child has outstanding work that is due soon'),
  (NULL,1,'datechanged','parent','the due date of a submission box is changed'),
  (NULL,1,'renamed','parent','a submission box is renamed'),

  (NULL,2,'open','student','a quiz that you need to attempt opens'),
  (NULL,2,'upcoming','student','you have yet to attempt a quiz that is due soon'),
  (NULL,2,'upcoming','parent','your child has yet to attempt a quiz that is due soon'),
  (NULL,2,'updated','student','a quiz is updated'),
  (NULL,2,'overdue','student','you failed to attempt a quiz'),
  (NULL,2,'overdue','parent','your child failed to attempt a quiz'),
  (NULL,2,'closed','staff','a quiz you posted has closed'),
  (NULL,2,'attempted','staff','a student attempts a quiz you posted'),
  (NULL,2,'marked','student','a teacher marks your attempt of a quiz'),
  (NULL,2,'marked','parent','a teacher marks your child\'s attempt of a quiz'),

  (NULL,3,'commented','student','someone comments on a blog post you have written'),
  (NULL,3,'followed','student','someone comments after you on a blog post'),
  (NULL,3,'followed','staff','someone comments after you on a blog post'),
  (NULL,3,'commented','staff','someone comments on a blog post you have written'),

  (NULL,4,'followed','student','someone comments after you on a forum'),
  (NULL,4,'followed','staff','someone comments after you on a forum'),
  (NULL,4,'mentioned','staff','someone mentions you on a forum'),
  (NULL,4,'mentioned','student','someone mentions you on a forum'),

  (NULL,5,'posted','student','someone posts school news'),
  (NULL,5,'posted','parent','someone posts school news'),
  (NULL,5,'posted','staff','someone posts school news'),

  (NULL,6,'upcoming','student','an event on your calendar is about to start'),
  (NULL,6,'upcoming','staff','an event on your calendar is about to start'),
  (NULL,6,'upcoming','parent','an event on your child\'s calendar is about to start'),
  (NULL,6,'updated','student','an event on your calendar is updated'),
  (NULL,6,'updated','staff','an event on your calendar is updated'),
  (NULL,6,'updated','parent','an event on your child\'s calendar is updated'),

  (NULL,7,'student_requested','staff','a student requests boarder leave'),
  (NULL,7,'parent_requested','staff','a parent requests boarder leave for their child'),
  (NULL,7,'approved','student','your boarder leave is approved'),
  (NULL,7,'approved','parent','your child\'s boarder leave is approved'),
  (NULL,7,'rejected','student','your boarder leave is rejected'),
  (NULL,7,'rejected','parent','your child\'s boarder leave is rejected'),

  (NULL,10,'joined','staff','someone joins a group of which you are an admin'),
  (NULL,10,'left','staff','someone leaves a group of which you are an admin'),
  (NULL,10,'requested','staff','someone requests access to a group you moderate'),
  (NULL,10,'approved','staff','your request to join a moderated group is approved'),
  (NULL,10,'approved','student','your request to join a moderated group is approved'),
  (NULL,10,'approved','parent','your request to join a moderated group is approved'),
);
