
/*
 Migrate data from user and user_profile to sis_profile_user table of the new schema
 */
INSERT INTO `sis_user_profile` (external_id, gender, title, firstname, lastname, email)
    SELECT `user`.`synergy_id`, `user_profile`.`gender`, `user`.`title`, `user`.`firstname`, `user`.`lastname`,  `user_profile`.`email`
    FROM `user`
             LEFT JOIN `groups`       ON `user`.`group_id` = `groups`.`id`
             LEFT JOIN `user_profile` ON `user`.`id` = `user_profile`.`user_id`
    WHERE `groups`.`group_type`  = 'student'
       OR `groups`.`group_type` = 'parent'
       OR `groups`.`group_type` = 'staff';

/*
 Migrate data from user, user_profile and sis_user_profile_student to sis_profile_user table of the new schema
 */
INSERT INTO `sis_user_profile_student` (`sis_user_profile_id`, `date_of_birth`, `ib`, `individual_learning_plans`, `esl`,
                                        `head_of_house_id`, `head_of_house`, `house`, `house_description`, `form_name`, `form_description`, `teacher_form_id`, `teacher_form_name`,
                                        `tutor_id`, `tutor_name`, `tutor_group`, `tutor_description`, `year_level`, `year_level_coord_id`, `year_level_coordinator`,
                                        `medical_alert`,`alert_message`, `mobile`, `special_needs`, `studies_code`, `court_order_type`, `boarding_status`, `comment`, `enrolment_date`, `leaving_date`)
    SELECT `sis_user_profile`.`id`, `user_profile`.`dob`, `user_profile`.`ib_flag`, NULL,`user_profile`.`esl_flag`, NULL, NULL,`user`.`house`, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
           `user_profile`.`level`, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, `user`.`boarding_status`, NULL, NULL, NULL
    FROM `user`
             JOIN `sis_user_profile` ON `sis_user_profile`.`external_id` = `user`.`synergy_id`
             JOIN `user_profile` ON `user`.`id` = `user_profile`.`user_id`
             JOIN `groups` ON `user`.`group_id` = `groups`.`id`
    WHERE `groups`.`group_type` = 'student';

/*
 Migrate data from user to sis_profile_user_parent table of the new schema
 */
INSERT INTO `sis_user_profile_parent` (`sis_user_profile_id`, `mobile`, `phone_home`, `phone_work`, `email`)
    SELECT `sis_user_profile`.`id`, NULL, NULL, NULL, `user`.`email_pass`
    FROM `user`
             JOIN `sis_user_profile` ON `sis_user_profile`.`external_id` = `user`.`synergy_id`
             JOIN `groups` ON `user`.`group_id` = `groups`.`id`
    WHERE `groups`.`group_type` = 'parent';

/*
 Migrate data from user to sis_profile_user_staff table of the new schema
 */
INSERT INTO `sis_user_profile_staff` (`sis_user_profile_id`, `category`, `position_title`,`position_description`, `department`, `building`, `room`, `phone`,`work_email`)
    SELECT `sis_user_profile`.`id`, NULL, `user`.`position_title`,NULL, NULL, NULL, NULL, NULL, `user`.`alt_email`
    FROM `user`
             JOIN `sis_user_profile` ON `sis_user_profile`.`external_id` = `user`.`synergy_id`
             JOIN `groups` ON `user`.`group_id` = `groups`.`id`
    WHERE `groups`.`group_type` = 'staff';

/*
 Migrate data from user_relation to sis_profile_relation table of the new schema
 */
INSERT INTO `sis_profile_relation` (`child_profile_id`, `user_profile_id`, `emergency_contact`, `type`)
    SELECT up1.id as child_id, up2.id as parent_id, NULL, 0
    FROM user_relation
    JOIN user user1 ON user_relation.user_id = user1.id
    JOIN sis_user_profile up1 ON user1.synergy_id = up1.external_id
    JOIN user user2 ON user_relation.relation_id = user2.id
    JOIN sis_user_profile up2 ON user2.synergy_id = up2.external_id