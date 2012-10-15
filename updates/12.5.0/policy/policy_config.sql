ALTER TABLE `config` CHANGE `type` `type` ENUM('Site','Security','Policy','Paths','Resources','Email','Welcome','Localisation','External DB','Colours','Board Leave') CHARSET utf8 COLLATE utf8_general_ci NULL;


UPDATE `config` SET `type`='Policy' WHERE `name`='policy';
UPDATE `config` SET `type`='Policy' WHERE `name`='news_edit_other';
UPDATE `config` SET `type`='Policy' WHERE `name`='group_staff_only';
UPDATE `config` SET `type`='Policy' WHERE `name`='protect_teacher_photo';
UPDATE `config` SET `type`='Policy' WHERE `name`='protect_student_photo';
UPDATE `config` SET `type`='Policy' WHERE `name`='news_staff_post_enabled';
UPDATE `config` SET `type`='Policy' WHERE `name`='news_default_expiry';
UPDATE `config` SET `type`='Policy' WHERE `name`='restrict_student_submissions';
UPDATE `config` SET `type`='Policy' WHERE `name`='restrict_parent_directory';