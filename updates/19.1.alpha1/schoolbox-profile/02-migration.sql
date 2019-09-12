/*
 Change the collation of synergy_id column
 */
ALTER TABLE `user`
    MODIFY synergy_id VARCHAR(64)
        CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci;

/*
 Migrate data from user and user_profile to sis_profile_user table of the new schema
 */
INSERT INTO `sis_user_profile` (external_id, title, firstname, lastname, created_at, updated_at)
    SELECT `user`.`synergy_id`, `user`.`title`, `user`.`firstname`, `user`.`lastname`, NOW(), NOW()
    FROM `user`
             LEFT JOIN `groups`       ON `user`.`group_id` = `groups`.`id`
             LEFT JOIN `user_profile` ON `user`.`id` = `user_profile`.`user_id`
    WHERE (synergy_id IS NOT NULL AND synergy_id != '') AND
         (`groups`.`group_type` = 'student'
       OR `groups`.`group_type` = 'parent'
       OR `groups`.`group_type` = 'staff');

/*
 Migrate data from user, user_profile and sis_user_profile_student to sis_profile_user table of the new schema
 */
INSERT INTO `sis_user_profile_student` (`sis_user_profile_id`, `date_of_birth`, `username`, `email`, `gender`, `campus_name`, `head_of_house_id`, `head_of_house_name`, `house_name`, `form_name`,
                                        `form_teacher_id`, `form_teacher_name`,`tutor_group`, `tutor_teacher_id`, `tutor_teacher_name`, `year_level`,
                                        `year_level_coordinator_id`, `year_level_coordinator_name`,`mobile`, `studies_code`,`alert_medical`,`alert_comment`,
                                        `alert_message`,`alert_legal`,`enrolment_date`,`leaving_date`, `ib`, `individual_learning_plans`,`esl`, `special_needs`,
                                        `boarding_status`, `created_at`, `updated_at`)
    SELECT `sis_user_profile`.`id`, `user_profile`.`dob`, `user`.`username`, `user`.`alt_email`,`user_profile`.`gender`,`luCampus`.`name`, NULL, NULL,`house`.`house`, NULL, NULL, NULL, NULL, NULL, NULL,
           `user_profile`.`level`, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,`user_profile`.`ib_flag`, NULL,`user_profile`.`esl_flag`,
    NULL, `user`.`boarding_status` , NOW(), NOW()
    FROM `user`
             JOIN `sis_user_profile` ON `sis_user_profile`.`external_id` = `user`.`synergy_id`
             JOIN `user_profile` ON `user`.`id` = `user_profile`.`user_id`
             JOIN `groups` ON `user`.`group_id` = `groups`.`id`
             LEFT JOIN `house`  ON `user`.`house` = `house`.`id`
             JOIN `userCampus`  ON `user`.`id` = `userCampus`.`uid`
             JOIN `luCampus`    ON `userCampus`.`campus` = `luCampus`.`id`
    WHERE `groups`.`group_type` = 'student'
    GROUP BY `userCampus`.`uid`
    ORDER BY `userCampus`.`uid`;

/*
 Migrate data from user to sis_profile_user_parent table of the new schema
 */
INSERT INTO `sis_user_profile_parent` (`sis_user_profile_id`, `mobile`, `phone_home`, `phone_work`, `username`, `email`, created_at, updated_at)
    SELECT `sis_user_profile`.`id`, NULL, NULL, NULL,`user`.`username`, `user`.`alt_email`, NOW(), NOW()
    FROM `user`
             JOIN `sis_user_profile` ON `sis_user_profile`.`external_id` = `user`.`synergy_id`
             JOIN `groups` ON `user`.`group_id` = `groups`.`id`
    WHERE `groups`.`group_type` = 'parent';

/*
 Migrate data from user to sis_profile_user_staff table of the new schema
 */
INSERT INTO `sis_user_profile_staff` (`sis_user_profile_id`, `username`, `email`,`category`, `position_title`, `department`, `phone`,
                                      `room`, `building`, `campus_name`, created_at, updated_at)
    SELECT `sis_user_profile`.`id`,`user`.`username`, `user`.`alt_email`, NULL, `user`.`position_title`, NULL, NULL, NULL, NULL, `luCampus`.`name`, NOW(), NOW()
    FROM `user`
             JOIN `sis_user_profile` ON `sis_user_profile`.`external_id` = `user`.`synergy_id`
             JOIN `groups` ON `user`.`group_id` = `groups`.`id`
             JOIN `userCampus`  ON `user`.`id` = `userCampus`.`uid`
             JOIN `luCampus`    ON `userCampus`.`campus` = `luCampus`.`id`
    WHERE `groups`.`group_type` = 'staff'
    GROUP BY `userCampus`.`uid`
    ORDER BY `userCampus`.`uid`;

/*
 Migrate data from user_relation to sis_user_profile_relation table of the new schema
 */
INSERT INTO `sis_user_profile_relation` (`child_profile_id`, `user_profile_id`, `emergency_contact`, `type`)
    SELECT up1.id as child_id, up2.id as parent_id, 0, 0
    FROM user_relation
    JOIN user user1 ON user_relation.user_id = user1.id
    JOIN sis_user_profile up1 ON user1.synergy_id = up1.external_id
    JOIN user user2 ON user_relation.relation_id = user2.id
    JOIN sis_user_profile up2 ON user2.synergy_id = up2.external_id
