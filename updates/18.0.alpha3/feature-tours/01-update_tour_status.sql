DROP table tour_status;

CREATE TABLE tour_status (
	user_id int(4) unsigned NOT NULL,
    tour_uuid varchar(36) NOT NULL,
    status ENUM('started', 'dismissed', 'completed', 'disabled') NOT NULL,
    created_at DATETIME NOT NULL,

    CONSTRAINT user_tour_status UNIQUE (user_id, tour_uuid),
    CONSTRAINT `tour_status_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
    CONSTRAINT `tour_status_tour_uuid_fk` FOREIGN KEY (`tour_uuid`) REFERENCES `tour` (`uuid`)

);

