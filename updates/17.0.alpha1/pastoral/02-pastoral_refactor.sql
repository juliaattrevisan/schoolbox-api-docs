-- Create tables to hold document classifications from SIS

CREATE TABLE lu_pastoral_document_classification
(
  id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name VARCHAR(100),
  external_code VARCHAR(50),
  deleted_at DATETIME
);

ALTER TABLE `lu_pastoral_type` ADD `pastoral_document_classification_id` INT NULL;
ALTER TABLE `lu_pastoral_type` MODIFY COLUMN `pastoral_document_classification_id` INT AFTER id;

-- Dates might be needed at some point for auditing...

ALTER TABLE `pastoral_access` ADD `created_at` DATETIME NULL;
ALTER TABLE `pastoral_access` MODIFY COLUMN `deleted_at` DATETIME AFTER created_at;
ALTER TABLE `pastoral_access` ADD `group_id` INT NULL;
ALTER TABLE `pastoral_access` MODIFY COLUMN `group_id` INT AFTER `pastoral_id`;

-- I have... no... frigging... idea... why this field exists... and it's totally my fault it exists ...FAIL!

ALTER TABLE pastoral DROP pastoral_id;

-- Create table to hold new pastoral groups

CREATE TABLE `pastoral_group`
(
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `user_id` INT,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  `deleted_at` DATETIME
);

-- Create table to hold the members of those groups

CREATE TABLE `pastoral_group_member`
(
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `group_id` INT,
  `author_id` INT,
  `created_at` DATETIME,
  `deleted_at` DATETIME
);

-- Create an audit table showing capturing who has viewed various pastoral records and searched
-- for various things

CREATE TABLE `pastoral_audit`
(
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `student_id` INT,
  `report_id` INT,
  `pastoral_id` INT,
  `search` VARCHAR(255),
  `search_result` TEXT,
  `created_at` DATETIME
);

-- Add an option to the severity table that will make items show on dashboard

ALTER TABLE `lu_pastoral_severity` ADD `dashboard` INT DEFAULT 0 NULL;

-- Remove unnecessary column from upload queue

ALTER TABLE `pastoral_upload_queue` DROP `type`;

-- Name tables more sensibly

ALTER TABLE pastoral_tag RENAME TO lu_pastoral_tag;
ALTER TABLE pastoral_record_tag RENAME TO pastoral_tags;

-- Add crud dates to cover our butts

ALTER TABLE pastoral_tags ADD created_at DATETIME NULL;
ALTER TABLE pastoral_tags ADD deleted_at DATETIME NULL;

-- Keep revision history of all changes to pastoral records

CREATE TABLE pastoral_revision
(
  id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  root_id INT(11),
  parent_id INT(11),
  owner_id INT(11),
  severity_id INT(11),
  student_id INT(11) NOT NULL,
  type_id INT(11),
  external_id INT(11),
  private TINYINT(4) DEFAULT '0',
  heading VARCHAR(255),
  body TEXT,
  created_at DATETIME DEFAULT '0000-00-00 00:00:00',
  updated_at DATETIME DEFAULT '0000-00-00 00:00:00',
  deleted_at DATETIME NULL,
  category_id INT(11),
  points FLOAT DEFAULT '0',
  modified_by_user_id INT(11),
  CONSTRAINT fk_pastoral_revision_severity FOREIGN KEY (severity_id) REFERENCES lu_pastoral_severity (id),
  CONSTRAINT fk_pastoral_revision_type FOREIGN KEY (type_id) REFERENCES lu_pastoral_type (id)
);

ALTER TABLE pastoral MODIFY points FLOAT DEFAULT '0';

CREATE INDEX fk_revision_student ON pastoral_revision (student_id);
CREATE INDEX fk_revision_type ON pastoral_revision (type_id);

ALTER TABLE `pastoral` ADD `modified_by_user_id` INT NULL;

-- Private pastoral record text moved to config variable

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES (
  'pastoral_private_body',
  '<p>This record has been marked as private, please contact the author of this record if you require further information.</p>',
  'Private Pastoral Record Text',
  'The text that will displayed to unauthorized users when viewing a private pastoral record.', 8, '', 5, 'true', 'false', 'false');

-- Create table to hold saved reports

CREATE TABLE `pastoral_report`
(
  `id` INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `owner_id` INT(11),
  `name` VARCHAR(255),
  `description` VARCHAR(255),
  `before_date` DATETIME,
  `after_date` DATETIME,
  `severity_id` INT(11),
  `involved` TINYINT,
  `types` TEXT,
  `authors` TEXT,
  `students` TEXT,
  `tags` TEXT,
  `order` TEXT,
  `years` TEXT,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  `deleted_at` DATETIME
);

-- Add table to allow reports available to be made available to other pastoral groups

CREATE TABLE `pastoral_report_groups`
(
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `report_id` INT,
  `group_id` INT,
  `created_at` DATETIME,
  `deleted_at` DATETIME
);

-- Allow disabling of severity in pastoral system

INSERT INTO `config` (
  name,
  value,
  title,
  description,
  field_id,
  options,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'pastoral_severity',
  '1',
  'Allow specification of severity on pastoral records.',
  'Disabling this will remove all references to the pastoral records severity from the system.',
  3,
  '',
  5,
  'true',
  'false',
  'false'
);

-- Make pastoral severity a non mandatory field

ALTER TABLE `pastoral` MODIFY `severity_id` INT(11);

INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('pastoral_page_size', '15', 'Records On Pastoral Page', 'The number of records to display on each page of a students pastoral care record.', 1, null, 5, 'true', 'false', 'false');
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required) VALUES ('pastoral_page_visible', '15', 'Number Of Pages Visible On Pastoral Paginator', 'The number of pages to display on the pastoral care paginator.', 1, null, 5, 'true', 'false', 'false');

UPDATE config SET description = 'Enables the writeback of pastoral records to your SIS.' WHERE `name` = 'external_pastoral';