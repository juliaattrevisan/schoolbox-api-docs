CREATE TABLE `tour_audience_role`
(

  `id`              INT               AUTO_INCREMENT    PRIMARY KEY,
  `author_user_id`  INT(4) UNSIGNED   NOT NULL,
  `tour_uuid`       VARCHAR(36)       NOT NULL,
  `role_id`         INT(4) UNSIGNED   NOT NULL,
  `created_at`      DATETIME          NULL,
  `deleted_at`      DATETIME          NULL,

  CONSTRAINT `tour_audience_role_author_user_id_fk` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`id`),
  CONSTRAINT `tour_audience_role_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`),
  CONSTRAINT `tour_audience_role_group_id_fk` FOREIGN KEY (`role_id`) REFERENCES `groups` (`id`)
)

CREATE INDEX `tour_audience_role_author_user_idx`	on `tour_audience_role` (`author_user_id`);
CREATE INDEX `tour_audience_role_tour_uuid_idx`	on `tour_audience_role` (`author_user_id`);
CREATE INDEX `tour_audience_role_group_idx` on `tour_audience_role` (`role_id`);