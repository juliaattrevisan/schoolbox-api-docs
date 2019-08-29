-- Profile Relation tuple is unique
-- your profile can't be related to the another profile multiple times
ALTER TABLE `sis_user_profile_relation`
    ADD UNIQUE INDEX `sis_unique_relation` (`child_profile_id` ASC, `user_profile_id` ASC);
