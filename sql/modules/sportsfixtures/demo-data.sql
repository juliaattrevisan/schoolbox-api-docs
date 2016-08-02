/* Make script idempotent (Reverse order of CREATE) */
DELETE FROM sportsfixtures_team_coach;
DELETE FROM sportsfixtures_team_participantcoach;
DELETE FROM sportsfixtures_coach;
DELETE FROM sportsfixtures_school;
DELETE FROM sportsfixtures_team_participantposition;
DELETE FROM sportsfixtures_team_position;
DELETE FROM sportsfixtures_team_participantuser;
DELETE FROM sportsfixtures_team_user;
DELETE FROM sportsfixtures_result;
DELETE FROM sportsfixtures_status;
DELETE FROM sportsfixtures_match_itineraryitem;
DELETE FROM sportsfixtures_match;
DELETE FROM sportsfixtures_venue;
DELETE FROM sportsfixtures_division_user;
DELETE FROM sportsfixtures_position;
DELETE FROM sportsfixtures_team;
DELETE FROM sportsfixtures_division;
DELETE FROM sportsfixtures_season;
DELETE FROM sportsfixtures_sport;

ALTER TABLE sportsfixtures_team_coach AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_team_participantcoach AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_coach AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_school AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_team_participantposition AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_team_position AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_team_participantuser AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_team_user AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_result AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_status AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_match_itineraryitem AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_match AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_venue AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_division_user AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_position AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_team AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_division AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_season AUTO_INCREMENT = 1;
ALTER TABLE sportsfixtures_sport AUTO_INCREMENT = 1;





/* Add some Sports
 */
INSERT INTO `sportsfixtures_sport` (`name`, `url`, `created_at`, `updated_at`) VALUES ('Soccer', 'https://www.youtube.com/watch?v=L4aBqvbP_MM', NOW(), NOW());
INSERT INTO `sportsfixtures_sport` (`name`, `url`, `created_at`, `updated_at`) VALUES ('AFL', 'https://www.youtube.com/watch?v=kuKV02aH3e4', NOW(), NOW());
INSERT INTO `sportsfixtures_sport` (`name`, `url`, `created_at`, `updated_at`) VALUES ('Rowing', 'https://www.youtube.com/watch?v=1zInfax6hpk', NOW(), NOW());
INSERT INTO `sportsfixtures_sport` (`name`, `url`, `created_at`, `updated_at`) VALUES ('Caber Tossing', 'https://www.youtube.com/watch?v=xb0FU8rSisU', NOW(), NOW());
INSERT INTO `sportsfixtures_sport` (`name`, `url`, `created_at`, `updated_at`) VALUES ('Tiddlywinks Everything Empty', 'https://www.youtube.com/watch?v=09TSwVSl3ZM', NOW(), NOW());

/* Add some seasons */
INSERT INTO `sportsfixtures_season` (`name`, `start`, `end`, `created_at`, `updated_at`) VALUES ('Summer 2016', '2016-01-01', '2016-06-30', NOW(), NOW());
INSERT INTO `sportsfixtures_season` (`name`, `start`, `end`, `created_at`, `updated_at`) VALUES ('Winter 2016', '2016-07-01', '2016-12-31', NOW(), NOW());
INSERT INTO `sportsfixtures_season` (`name`, `start`, `end`, `created_at`, `updated_at`) VALUES ('Summer 2017', '2017-01-01', '2017-06-30', NOW(), NOW());
INSERT INTO `sportsfixtures_season` (`name`, `start`, `end`, `created_at`, `updated_at`) VALUES ('Winter 2017', '2017-07-01', '2017-12-31', NOW(), NOW());


/* Add some Venues
 */
INSERT INTO `sportsfixtures_venue` (`name`, `latitude`, `longitude`, `address`, `created_at`, `updated_at`) VALUES ('The Office', '-37.8320323', '145.0563255', 'Level 6, 697 Burke Rd, Camberwell VIC 3124', NOW(), NOW());
INSERT INTO `sportsfixtures_venue` (`name`, `latitude`, `longitude`, `address`, `created_at`, `updated_at`) VALUES ('Haileybury', '-37.9962304', '145.1451713', '855 Springvale Rd, Keysborough VIC 3173', NOW(), NOW());
INSERT INTO `sportsfixtures_venue` (`name`, `latitude`, `longitude`, `address`, `created_at`, `updated_at`) VALUES ('Scotch College', '-37.8357034', '145.029449', '1 Morrison St, Hawthorn VIC 3122', NOW(), NOW());
INSERT INTO `sportsfixtures_venue` (`name`, `latitude`, `longitude`, `address`, `created_at`, `updated_at`) VALUES ('Moriah College', '-33.9002482', '151.2423634', 'Queens Park Rd, Queens Park NSW 2022', NOW(), NOW());
INSERT INTO `sportsfixtures_venue` (`name`, `latitude`, `longitude`, `address`, `created_at`, `updated_at`) VALUES ('Broome Golf Club', '-17.9801122', '122.2161393', 'Unit 4 / 71a Milperra Road, Broome WA 6725', NOW(), NOW());
INSERT INTO `sportsfixtures_venue` (`name`, `latitude`, `longitude`, `address`, `created_at`, `updated_at`) VALUES ('Melbourne Sports Aquatic Centre', '-37.8419501', '144.9604905', '30 Aughtie Dr, Melbourne VIC 3206', NOW(), NOW());



/* Add some arbitrary Schools
 */
insert  into `sportsfixtures_school`(`id`,`name`,`created_at`,`updated_at`,`deleted_at`) values (1,'Moriah','2016-07-07 19:58:53','2016-07-07 19:58:53',NULL),(2,'Scotch','2016-07-07 19:58:59','2016-07-07 19:58:59',NULL),(3,'Wesley','2016-07-07 19:59:05','2016-07-07 19:59:05',NULL);




/* All Positions for Soccer
 */
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Goalkeeper', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Sweeper', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Centre Back', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Left Mid', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Centre Mid', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Right Mid', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Left Wing', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Right Wing', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 'Centre Forward', NOW(), NOW());

/* Three Divisions for Soccer
 */
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 2, 'Open', NOW(), NOW());
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 2, 'Year 9', NOW(), NOW());
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('1', 2, 'Year 8', NOW(), NOW());

/* Three Teams for Soccer Open
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('1', 'First XI', 'http://1st-soccer.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('1', 'Second XI', 'http://2nd-soccer.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('1', 'Third XI', 'http://3rd-soccer.alaress.com.au', NOW(), NOW());

/* Two Teams for Soccer Year 9
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('2', '9A', 'http://9a-soccer.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('2', '9B', 'http://9b-soccer.alaress.com.au', NOW(), NOW());

/* Two Teams for Soccer Year 8
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('3', '8A', 'http://8a-soccer.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('3', '8B', 'http://8b-soccer.alaress.com.au', NOW(), NOW());




/* All Positions for AFL
 */
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Centre Half-Forward', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Half-Forward', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Forward Pocket', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Full Forward', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Ruck', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Centre', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Ruck-Rover', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Rover', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Wing', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Centre Half-Back', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Half-Back', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Back Pocket', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 'Full Back', NOW(), NOW());

/* Two Divisions for AFL
 */
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 2, 'Open', NOW(), NOW());
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('2', 2, 'Year 9', NOW(), NOW());

/* Two Teams for AFL Open
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('4', 'First XVIII', 'http://1st-afl.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('4', 'Second XVIII', 'http://2nd-afl.alaress.com.au', NOW(), NOW());

/* Four Teams for AFL Year 9
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('5', '9A', 'http://9a-afl.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('5', '9B', 'http://9b-afl.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('5', '9C', 'http://9c-afl.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('5', '9D', 'http://9d-afl.alaress.com.au', NOW(), NOW());

/* Demo assignations: Lobby
 */
INSERT INTO `sportsfixtures_division_user`(`id`,`division_id`,`user_id`,`sequence`) values (1,5,156,0),(2,5,230,0),(3,4,16,1),(4,4,17,11),(5,4,188,14),(6,4,193,3),(7,4,194,13),(8,4,195,2),(9,4,197,12),(10,4,201,10),(11,4,204,20),(12,4,218,9),(13,4,221,19),(14,4,223,8),(15,4,226,18),(16,4,229,7),(17,4,230,17),(18,4,233,6),(19,4,235,16),(20,4,237,5),(21,4,238,15),(22,4,240,4);

/* Demo assignations: Team
 */
INSERT INTO `sportsfixtures_team_user`(`id`,`team_id`,`user_id`,`sequence`) VALUES (6,12,230,1),(7,8,204,1),(8,8,221,11),(9,8,226,10),(10,8,230,9),(11,8,235,8),(12,8,238,7),(13,8,188,6),(14,8,194,5),(15,8,197,4),(16,8,17,3),(17,9,201,1),(18,9,218,10),(19,9,223,9),(20,9,229,2),(21,9,233,8),(22,9,237,7),(23,9,240,5),(24,9,193,6),(25,9,195,4),(26,9,16,3);
INSERT INTO `sportsfixtures_team_position`(`id`,`team_id`,`user_id`,`position_id`) VALUES (11,8,17,11),(12,8,17,12),(6,8,188,14),(7,8,194,15),(8,8,194,16),(9,8,197,16),(10,8,197,17),(26,8,204,12),(2,8,221,10),(1,8,226,11),(3,8,230,12),(4,8,235,12),(5,8,238,13),(21,9,16,21),(18,9,193,19),(20,9,195,21),(25,9,201,22),(13,9,218,18),(14,9,223,18),(22,9,229,20),(23,9,229,21),(24,9,229,22),(15,9,233,18),(16,9,233,19),(17,9,237,18),(19,9,240,20);

/* Matches
 */
INSERT INTO `sportsfixtures_match`(`id`,`division_id`, `season_id`, `venue_id`,`home_team_id`,`away_team_id`,`home_school_id`,`away_school_id`,`name`,`start_at`,`created_at`,`updated_at`,`deleted_at`) VALUES (1,4,2,6,8,9,NULL,NULL,'First Match Ever','2016-07-08 11:00:00','2016-07-07 19:57:35','2016-07-07 19:57:35',NULL),(2,4,2,3,8,NULL,NULL,2,'Grand Final','2016-07-30 16:00:00','2016-07-07 20:06:41','2016-07-07 20:06:41',NULL);

/* Itineraries
 */
INSERT INTO `sportsfixtures_match_itineraryitem`(`id`,`match_id`,`description`,`start_at`,`created_at`,`updated_at`,`deleted_at`) VALUES (1,2,'Go to the place where the sportsing will happen','2016-07-30 15:00:00','2016-07-07 20:09:13','2016-07-07 20:09:13',NULL),(2,2,'Prepare for the sportsing','2016-07-30 15:30:00','2016-07-07 20:09:30','2016-07-07 20:09:30',NULL),(3,2,'Grand Final Sportsing','2016-07-30 16:00:00','2016-07-07 20:09:54','2016-07-07 20:09:54',NULL),(4,2,'Closing Ceremony of Sportsing','2016-07-30 18:30:00','2016-07-07 20:10:13','2016-07-07 20:10:13',NULL),(5,1,'Go to the place and do the thing','2016-07-08 10:30:00','2016-07-07 20:12:04','2016-07-07 20:12:04',NULL);

/* Results
 */
INSERT INTO `sportsfixtures_result`(`id`,`status_id`,`match_id`,`team_id`,`description`,`created_at`,`updated_at`,`deleted_at`) VALUES (1,1,1,8,'<p><em><strong>We sportsed well.</strong></em></p>\r\n','2016-07-07 20:02:54','2016-07-07 20:02:54',NULL),(2,2,1,9,'<p><em>We didn&#39;t sports very well&nbsp;</em></p>\r\n\r\n<p><em>We should have sportsed better</em></p>\r\n','2016-07-07 20:03:22','2016-07-07 20:03:22',NULL);

/* Coaches
 */
INSERT INTO `sportsfixtures_coach`(`id`,`name`,`user_id`,`created_at`,`updated_at`,`deleted_at`) VALUES (1,'Boaty McBoatface',NULL,'2016-07-19 12:57:12','2016-07-19 12:57:12',NULL),(2,'Ms Celia Glenn',3,'2016-07-19 12:57:12','2016-07-19 12:57:12',NULL),(3,'Miss Jackie Benson',247,'2016-07-19 12:57:12','2016-07-19 12:57:12',NULL);
INSERT INTO `sportsfixtures_team_coach`(`sportsfixtures_team_id`,`sportsfixtures_coach_id`) VALUES (8,1),(8,2),(8,3);


/* All Positions for Rowing
 */
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', 'Cox', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', 'Stroke', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '#7', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '#6', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '#5', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '#4', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '#3', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', '#2', NOW(), NOW());
INSERT INTO `sportsfixtures_position` (`sport_id`, `name`, `created_at`, `updated_at`) VALUES ('3', 'Bow', NOW(), NOW());

/* Three Divisions for Rowing
 */
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('3', 2, 'Open', NOW(), NOW());
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('3', 2, 'Year 9', NOW(), NOW());
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('3', 2, 'Year 8', NOW(), NOW());

/* Three Teams for Rowing Open
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('6', 'First VIII', 'http://1st-9-rowing.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('6', 'Second VIII', 'http://2nd-9-rowing.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('6', 'Third VIII', 'http://3rd-9-rowing.alaress.com.au', NOW(), NOW());

/* Two Teams for Rowing Year 9
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('7', '9A', 'http://9a-rowing.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('7', '9B', 'http://9b-rowing.alaress.com.au', NOW(), NOW());

/* Three Teams for Rowing Year 8
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('8', '8A', 'http://8a-rowing.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('8', '8B', 'http://8b-rowing.alaress.com.au', NOW(), NOW());
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('8', '8C', 'http://8c-rowing.alaress.com.au', NOW(), NOW());




/* No Positions for Caber
 */

/* One Division for Caber
 */
INSERT INTO `sportsfixtures_division` (`sport_id`, `season_id`, `name`, `created_at`, `updated_at`) VALUES ('4', 2, 'Open', NOW(), NOW());

/* One Team for Caber
 */
INSERT INTO `sportsfixtures_team` (`division_id`, `name`, `url`, `created_at`, `updated_at`) VALUES ('9', 'Caber Tossing Elite', 'http://caber.alaress.com.au', NOW(), NOW());




/* No Positions for Tiddlywinks
 */

/* No Divisions for Tiddlywinks
 */

/* No Teams for Tiddlywinks
 */

