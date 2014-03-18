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
  `routing_key`   VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX (`namespace`, `key`, `role_type`),
  INDEX (`namespace`, `key`),
  INDEX (`key`),
  INDEX (`namespace`),
  INDEX (`role_type`)
) CHARSET=utf8 COLLATE=utf8_bin;

CREATE TABLE `msg_reroute`(
  `relation_id`    INT UNSIGNED NOT NULL,
  `relation_type`  ENUM('user','role','campus') NOT NULL,
  `routing_key`    VARCHAR(255) NOT NULL,
  `incoming_level` INT UNSIGNED NOT NULL,
  `outgoing_level` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`relation_id`, `relation_type`, `routing_key`, `incoming_level`),
  INDEX (`relation_id`, `relation_type`)
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

INSERT INTO `msg_source`(`id`,`namespace`,`key`,`role_type`,`description`,`routing_key`,`default_level`) VALUES
  (1,1,'open','student','a submission box that you need to submit to opens','notify.category.*.#.box.*.open',250),
  (2,1,'upcoming','student','you have outstanding work that is due soon','notify.category.*.#.box.*.upcoming',300),
  (3,1,'updated','student','a submission box is updated','notify.category.*.#.box.*.updated',250),
  (4,1,'overdue','student','you have outstanding work that is overdue','notify.category.*.#.box.*.overdue',300),
  (5,1,'closed','staff','a submission box has closed','notify.category.*.#.box.*.closed',300),
  (6,1,'submitted','staff','a student submits work','notify.category.*.#.box.*.submitted',200),
  (7,1,'returned','student','a teacher marks your work','notify.category.*.#.box.*.returned',250),
  (8,1,'returned','parent','a teacher marks your child\'s work','notify.category.*.#.box.*.returned',200),
  (9,1,'overdue','parent','your child has outstanding work that is overdue','notify.category.*.#.box.*.overdue',300),
  (10,1,'upcoming','parent','your child has outstanding work that is due soon','notify.category.*.#.box.*.upcoming',250),
  (11,2,'open','student','an online assessment that you need to attempt opens','notify.category.*.#.assess.*.open',250),
  (12,2,'upcoming','student','you have yet to attempt an assessment that is due soon','notify.category.*.#.assess.*.upcoming',300),
  (13,2,'upcoming','parent','your child has yet to attempt an assessment that is due soon','notify.category.*.#.assess.*.upcoming',300),
  (14,2,'updated','student','an assessment is updated','notify.category.*.#.assess.*.updated',250),
  (15,2,'overdue','student','you failed to attempt an assessment','notify.category.*.#.assess.*.overdue',300),
  (16,2,'overdue','parent','your child failed to attempt an assessment','notify.category.*.#.assess.*.overdue',300),
  (17,2,'closed','staff','an assessment you posted has closed','notify.category.*.#.assess.*.closed',200),
  (18,2,'attempted','staff','a student attempts an assessment you posted','notify.category.*.#.assess.*.attempted',200),
  (19,2,'marked','student','a teacher marks your attempt of an assessment','notify.category.*.#.assess.*.marked',250),
  (20,2,'marked','parent','a teacher marks your child\'s attempt of an assessment','notify.category.*.#.assess.*.marked',250),
  (21,3,'commented','student','someone comments on a blog post you have written','notify.category.*.#.blog.*.commented',250),
  (22,3,'followed','student','someone comments after you on a blog post','notify.category.*.#.blog.*.followed',250),
  (23,3,'followed','staff','someone comments after you on a blog post','notify.category.*.#.blog.*.followed',250),
  (24,3,'commented','staff','someone comments on a blog post you have written','notify.category.*.#.blog.*.commented',250),
  (25,4,'followed','student','someone comments after you on a forum','notify.category.*.#.forum.*.followed',250),
  (26,4,'followed','staff','someone comments after you on a forum','notify.category.*.#.forum.*.followed',250),
  (27,4,'mentioned','staff','someone mentions you on a forum','notify.category.*.#.forum.*.mentioned',250),
  (28,4,'mentioned','student','someone mentions you on a forum','notify.category.*.#.forum.*.mentioned',250),
  (29,5,'posted','student','someone posts school news','notify.news.*.posted',200),
  (30,5,'posted','parent','someone posts school news','notify.news.*.posted',200),
  (31,5,'posted','staff','someone posts school news','notify.news.*.posted',200),
  (32,6,'upcoming','student','an event on your calendar is about to start','notify.calendar.*.upcoming',250),
  (33,6,'upcoming','staff','an event on your calendar is about to start','notify.calendar.*.upcoming',250),
  (34,6,'upcoming','parent','an event on your child\'s calendar is about to start','notify.calendar.*.upcoming',250),
  (35,6,'updated','student','an event on your calendar is updated','notify.calendar.*.updated',250),
  (36,6,'updated','staff','an event on your calendar is updated','notify.calendar.*.updated',250),
  (37,6,'updated','parent','an event on your child\'s calendar is updated','notify.calendar.*.updated',250),
  (38,7,'student_requested','staff','a student requests boarder leave','notify.leave.*.student_requested',300),
  (39,7,'parent_requested','staff','a parent requests boarder leave for their child','notify.leave.*.parent_requested',300),
  (40,7,'approved','student','your boarder leave is approved','notify.leave.*.approved',300),
  (41,7,'approved','parent','your child\'s boarder leave is approved','notify.leave.*.approved',300),
  (42,7,'rejected','student','your boarder leave is rejected','notify.leave.*.rejected',300),
  (43,7,'rejected','parent','your child\'s boarder leave is rejected','notify.leave.*.rejected',300);
