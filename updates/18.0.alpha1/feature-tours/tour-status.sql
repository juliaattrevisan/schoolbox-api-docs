CREATE TABLE tour_status(
    user_id         int(4)UNSIGNED NOT NULL,
    current_step    varchar(36) NOT NULL,
    url             varchar(255) NOT NULL,
    status          varchar(255) NOT NULL
);

ALTER TABLE `tour_status`
ADD CONSTRAINT `fk_tour_status_user_id`
FOREIGN KEY (`user_id`)
REFERENCES `user` (`id`)
ON DELETE NO ACTION
ON UPDATE NO ACTION,
ADD CONSTRAINT `fk_tour_status_current_step`
FOREIGN KEY (`current_step`)
REFERENCES `tour_step` (`uuid`)
ON DELETE NO ACTION
ON UPDATE NO ACTION;