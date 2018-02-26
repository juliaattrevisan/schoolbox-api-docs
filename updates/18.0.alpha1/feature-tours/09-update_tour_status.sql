DROP table tour_status;

CREATE TABLE tour_status (
	user_id int NOT NULL,
    tour_uuid varchar(36) NOT NULL,
    status ENUM('started', 'dismissed', 'completed', 'disabled') NOT NULL,
    created_at DATETIME NOT NULL
);