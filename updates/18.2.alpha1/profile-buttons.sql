CREATE TABLE profile_button
(
	id INT auto_increment
		PRIMARY KEY,
	sort_order INT NULL,
	title VARCHAR(200) charset utf8mb4 NULL,
	text VARCHAR(200) charset utf8mb4 NULL,
	icon VARCHAR(200) charset utf8mb4 NULL,
	url VARCHAR(2048) charset utf8mb4 NULL,
	target VARCHAR(200) charset utf8mb4 NULL,
	deleted_at DATETIME NULL
)
collate=utf8mb4_unicode_ci
;

CREATE TABLE profile_button_campus
(
	id INT auto_increment
		PRIMARY KEY,
	profile_button_id INT NOT NULL,
	campus_id tinyint(2) unsigned NOT NULL,
	CONSTRAINT fk_profile_button_campus_campus_id
		FOREIGN KEY (campus_id) REFERENCES luCampus (id),
	CONSTRAINT profile_button_campus_profile_button_id_fk
		FOREIGN KEY (profile_button_id) REFERENCES profile_button (id)
)
collate=utf8mb4_unicode_ci
;

CREATE index fk_profile_button_campus_campus_id_idx
	ON profile_button_campus (campus_id)
;

CREATE TABLE profile_button_role
(
	id INT auto_increment
		PRIMARY KEY,
	profile_button_id INT NOT NULL,
	role_id INT(11) unsigned NOT NULL,
	CONSTRAINT fk_profile_button_role_role_id
		FOREIGN KEY (role_id) REFERENCES groups (id),
	CONSTRAINT profile_button_role_profile_button_id_fk
		FOREIGN KEY (profile_button_id) REFERENCES profile_button (id)
)
collate=utf8mb4_unicode_ci
;

CREATE index fk_profile_button_role_role_id_idx
	ON profile_button_role (role_id)
;