-- Add config variables that hold default course page templates

INSERT INTO config
  (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES
  ('course_builder_default_course_page_template', '13', "Default Course Page Template", 'Default homepage template when creating course overview pages', null, null, null, 'false', 'true', 'false'),
  ('course_builder_default_unit_page_template', '14', "Default Unit Page Template", 'Default homepage template when creating course unit pages', null, null, null, 'false', 'true', 'false'),
  ('course_builder_default_course_page_inherit', '1', "Default Course Page Inheritance", 'Indicates if new course overview  pages inherit permissions from parent', null, null, null, 'false', 'true', 'false'),
  ('course_builder_default_unit_page_inherit', '1', "Default Unit Page Inheritance", 'Indicates if new course unit pages inherit permissions from parent', null, null, null, 'false', 'true', 'false');

-- Create table to hold default permissions for course pages

CREATE TABLE course_builder_config
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT,
    permission INT,
    page VARCHAR(255)
);

-- Default all permissions to none

INSERT INTO course_builder_config (role_id, permission, page) SELECT id, 0, 'course' FROM groups;
INSERT INTO course_builder_config (role_id, permission, page) SELECT id, 0, 'unit' FROM groups;
