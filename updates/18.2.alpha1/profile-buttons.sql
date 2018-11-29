create table profile_button
(
	id int auto_increment
		primary key,
	sort_order int null,
	title varchar(200) charset utf8mb4 null,
	text varchar(200) charset utf8mb4 null,
	token varchar(200) charset utf8mb4 null,
	icon varchar(200) charset utf8mb4 null,
	url varchar(2048) charset utf8mb4 null,
	target varchar(200) charset utf8mb4 null,
	deleted_at datetime null
)
collate=utf8mb4_unicode_ci
;

create table profile_button_campus
(
	id int auto_increment
		primary key,
	profile_button_id int not null,
	campus_id tinyint(2) unsigned not null,
	constraint fk_profile_button_campus_campus_id
		foreign key (campus_id) references luCampus (id),
	constraint profile_button_campus_profile_button_id_fk
		foreign key (profile_button_id) references profile_button (id)
)
collate=utf8mb4_unicode_ci
;

create index fk_profile_button_campus_campus_id_idx
	on profile_button_campus (campus_id)
;

create table profile_button_role
(
	id int auto_increment
		primary key,
	profile_button_id int not null,
	role_id int(11) unsigned not null,
	constraint fk_profile_button_role_role_id
		foreign key (role_id) references groups (id),
	constraint profile_button_role_profile_button_id_fk
		foreign key (profile_button_id) references profile_button (id)
)
collate=utf8mb4_unicode_ci
;

create index fk_profile_button_role_role_id_idx
	on profile_button_role (role_id)
;