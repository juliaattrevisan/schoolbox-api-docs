-- Profile Relation tuple is unique
-- your profile can't be related to the another profile multiple times

-- ALTER IGNORE will remove dupes
-- https://stackoverflow.com/questions/3311903/remove-duplicate-rows-in-mysql
ALTER IGNORE TABLE `sis_user_profile_relation`
    ADD UNIQUE INDEX `sis_unique_relation` (`child_profile_id` ASC, `user_profile_id` ASC);
