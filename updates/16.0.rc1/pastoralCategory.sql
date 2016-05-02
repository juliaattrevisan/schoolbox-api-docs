CREATE TABLE `lu_pastoral_category`
(
    `id` INT,
    `pastoral_type_id` INT,
    `name` VARCHAR(100),
    `external_code` VARCHAR(50),
    `external_id` INT,
    `deleted_at` DATETIME
);

ALTER TABLE `lu_pastoral_category` ADD PRIMARY KEY (id);
ALTER TABLE `lu_pastoral_category` MODIFY `id` INT(11) AUTO_INCREMENT;
ALTER TABLE `pastoral` ADD `category_id` INT NULL;

INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Academic'), 'Achievement/Award', null, null, null);
INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Academic'), 'Learning Difficulties', null, null, null);
INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Medical'), 'Emergency', null, null, null);
INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Medical'), 'On-going Condition', null, null, null);
INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Discipline'), 'Uniform Infraction', null, null, null);
INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Discipline'), 'Truancy', null, null, null);
INSERT INTO `lu_pastoral_category` (pastoral_type_id, name, external_code, external_id, deleted_at) VALUES ((SELECT `id` FROM `lu_pastoral_type` WHERE `name` = 'Discipline'), 'Behavioural Issues', null, null, null);
