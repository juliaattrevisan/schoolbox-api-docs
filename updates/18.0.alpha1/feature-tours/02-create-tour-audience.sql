-- Audience records are indexed by an integer- not a UUID. This is intentional. Alaress will never mandate that
-- a tour be displayed to any particular audience from a central

-- Audience feature tours to a Schoolbox user role

CREATE TABLE `tour_audience_role`
(
  `id`                INT               AUTO_INCREMENT    PRIMARY KEY,
  `moderator_user_id` INT(4) UNSIGNED   NOT NULL,                         -- This is the 'tour moderator' that added the audience record
  `tour_uuid`         VARCHAR(36)       NOT NULL,
  `role_id`           INT(4) UNSIGNED   NOT NULL,

  CONSTRAINT `tour_audience_role_moderator_user_id_fk` FOREIGN KEY (`moderator_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_audience_role_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`),
  CONSTRAINT `tour_audience_role_role_id_fk` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`)
);

CREATE INDEX `tour_audience_role_moderator_user_id_idx`	on `tour_audience_role` (`moderator_user_id`);
CREATE INDEX `tour_audience_role_tour_uuid_idx`	on `tour_audience_role` (`moderator_user_id`);
CREATE INDEX `tour_audience_role_role_id_idx` on `tour_audience_role` (`role_id`);

-- Additional feature tour permissions should be added via additional pivot tables
--
-- e.g. `tour_audience_campus`
--      `tour_audience_year_level`
--      ...
--      `tour_audience_xxxxxxxxxx`