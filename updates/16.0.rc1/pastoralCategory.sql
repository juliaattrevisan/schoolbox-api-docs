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
