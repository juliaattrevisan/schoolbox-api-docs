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

create table profile_icons
(
	name varchar(100) charset utf8mb4 not null
		primary key,
	title varchar(200) charset utf8mb4 null
)
	collate=utf8mb4_unicode_ci
;

INSERT INTO profile_icons (name, title) VALUES ('approve', 'Tick');
INSERT INTO profile_icons (name, title) VALUES ('attach', 'Paperclip');
INSERT INTO profile_icons (name, title) VALUES ('boarder-leave', 'Bed');
INSERT INTO profile_icons (name, title) VALUES ('breadcrumbs', 'Chevrons');
INSERT INTO profile_icons (name, title) VALUES ('calendar', 'Calendar');
INSERT INTO profile_icons (name, title) VALUES ('clear', 'Sun');
INSERT INTO profile_icons (name, title) VALUES ('close', 'Cross');
INSERT INTO profile_icons (name, title) VALUES ('cloudy', 'Cloud');
INSERT INTO profile_icons (name, title) VALUES ('collapse', 'Minus');
INSERT INTO profile_icons (name, title) VALUES ('comment', 'Speech Bubble');
INSERT INTO profile_icons (name, title) VALUES ('copy', 'Copy');
INSERT INTO profile_icons (name, title) VALUES ('course', 'Course');
INSERT INTO profile_icons (name, title) VALUES ('delete', 'Trash');
INSERT INTO profile_icons (name, title) VALUES ('division', 'Shield');
INSERT INTO profile_icons (name, title) VALUES ('download', 'Download');
INSERT INTO profile_icons (name, title) VALUES ('due-work', 'Tray');
INSERT INTO profile_icons (name, title) VALUES ('email', 'Envelope');
INSERT INTO profile_icons (name, title) VALUES ('eportfolio', 'Briefcase');
INSERT INTO profile_icons (name, title) VALUES ('favourite', 'Star');
INSERT INTO profile_icons (name, title) VALUES ('files', 'Papers');
INSERT INTO profile_icons (name, title) VALUES ('folder', 'Folder');
INSERT INTO profile_icons (name, title) VALUES ('forms', 'Form');
INSERT INTO profile_icons (name, title) VALUES ('google-drive', 'Google Drive');
INSERT INTO profile_icons (name, title) VALUES ('group', 'People');
INSERT INTO profile_icons (name, title) VALUES ('help', 'Help');
INSERT INTO profile_icons (name, title) VALUES ('link', 'Link');
INSERT INTO profile_icons (name, title) VALUES ('locked', 'Padlock');
INSERT INTO profile_icons (name, title) VALUES ('network', 'Network Drive');
INSERT INTO profile_icons (name, title) VALUES ('news', 'Newspaper');
INSERT INTO profile_icons (name, title) VALUES ('office-365', 'Office 365');
INSERT INTO profile_icons (name, title) VALUES ('open', 'Door');
INSERT INTO profile_icons (name, title) VALUES ('phone', 'Telephone');
INSERT INTO profile_icons (name, title) VALUES ('podcast', 'Podcast');
INSERT INTO profile_icons (name, title) VALUES ('print', 'Print');
INSERT INTO profile_icons (name, title) VALUES ('rain', 'Rain Cloud');
INSERT INTO profile_icons (name, title) VALUES ('report', 'Lightning');
INSERT INTO profile_icons (name, title) VALUES ('resource-booking', 'Camera');
INSERT INTO profile_icons (name, title) VALUES ('rss', 'RSS');
INSERT INTO profile_icons (name, title) VALUES ('schoolbox', 'Schoolbox');
INSERT INTO profile_icons (name, title) VALUES ('settings', 'Gear');
INSERT INTO profile_icons (name, title) VALUES ('showcase', 'Trophy');
INSERT INTO profile_icons (name, title) VALUES ('shuffle', 'Shuffle');
INSERT INTO profile_icons (name, title) VALUES ('sport', 'Sport');
INSERT INTO profile_icons (name, title) VALUES ('synergetic', 'Synergetic');
INSERT INTO profile_icons (name, title) VALUES ('teacher', 'Graduation Cap');
INSERT INTO profile_icons (name, title) VALUES ('timetable', 'Clock');
INSERT INTO profile_icons (name, title) VALUES ('twitter', 'Twitter');
INSERT INTO profile_icons (name, title) VALUES ('unlocked', 'Padlock (Open)');
INSERT INTO profile_icons (name, title) VALUES ('upload', 'Upload');
INSERT INTO profile_icons (name, title) VALUES ('user', 'Person');
INSERT INTO profile_icons (name, title) VALUES ('venue', 'Location');
INSERT INTO profile_icons (name, title) VALUES ('video', 'Video');
INSERT INTO profile_icons (name, title) VALUES ('view', 'Eye');
INSERT INTO profile_icons (name, title) VALUES ('wiki', 'Wiki');
INSERT INTO profile_icons (name, title) VALUES ('wolfram-alpha', 'Wolfram Alpha');