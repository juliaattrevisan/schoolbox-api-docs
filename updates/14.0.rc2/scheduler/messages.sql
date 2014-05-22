/* Data for the table `msg_namespace` */

INSERT INTO `msg_namespace`(`id`,`slug`,`label`) VALUES
  (10, 'group', 'Groups'),
  (11, 'cms', 'Content Management');

/* Data for `msg_source` */
TRUNCATE `msg_source`;

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

  (NULL,11,'created','staff',  'someone creates homepage content'),
  (NULL,11,'created','student','someone creates homepage content'),
  (NULL,11,'created','parent', 'someone creates homepage content'),
  (NULL,11,'updated','staff',  'someone updates homepage content'),
  (NULL,11,'updated','student','someone updates homepage content'),
  (NULL,11,'updated','parent', 'someone updates homepage content'),
  (NULL,11,'deleted','staff',  'someone deletes homepage content'),
  (NULL,11,'deleted','student','someone deletes homepage content'),
  (NULL,11,'deleted','parent', 'someone deletes homepage content');


/* DUEWORK AND QUIZ */
/* Students, all but upcoming and overdue are     min digest,      max email,       default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 200 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (1, 2)
AND role_type = 'student'
AND `key` NOT IN ('overdue');

/* Students, upcoming and overdue are          min instant,     max email,       default email */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 250 `min_level`, 300 `max_level`, 300 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (1, 2)
AND role_type = 'student'
AND `key` IN ('overdue');

/* Staff, everything                           min off,       max email,       default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (1, 2)
AND role_type = 'staff';

/* Parents, everything                         min off        max email        default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (1, 2)
AND role_type = 'parent';


/* BLOG, FORUM */
/* Staff and Student, everything               min off,       max email,       default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (3, 4)
AND role_type IN ('student', 'staff');

/* NEWS */
/* Everyone, everything                        min digest,      max email,       default digest */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 200 `min_level`, 300 `max_level`, 200 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (5);

/* CALENDAR */
/* Everyone, upcoming                          min off        max email        default off */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 0 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (6)
AND `key` = 'upcoming';

/* Everyone, upcoming                          min off        max email        default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (6)
AND `key` = 'updated';


/* BOARDER LEAVE */
/* Everyone, everything                        min off        max email        default email */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 300 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (7);


/* MEMBERSHIP */
/* Everyone, joined                            min off        max email        default instant */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 250 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (10)
AND `key` IN ('joined', 'approved');

/* Everyone, left                              min off        max email        default off */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 0 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (10)
AND `key` = 'left';

/* Everyone, requested                         min off        max email        default email */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 300 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (10)
AND `key` = 'requested';

/* CMS */
/* Everyone, requested                         min off        max email        default digest */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 200 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (11)
AND `key` IN ('created','updated');

/* Everyone, requested                         min off        max email        default off */
INSERT INTO `msg_route_perm` (`msg_source_id`, `role_id`, `min_level`, `max_level`, `default_level`)
SELECT src.id `msg_source_id`, g.id `role_id`, 0 `min_level`, 300 `max_level`, 0 `default_level`
FROM msg_source src
LEFT JOIN groups g ON src.`role_type` = g.`group_type`
WHERE namespace IN (11)
AND `key` IN ('deleted');

