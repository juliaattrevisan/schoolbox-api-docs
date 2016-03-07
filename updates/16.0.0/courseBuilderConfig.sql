-- Add config variables that hold default course page templates

INSERT INTO config
  (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES
  ('default_course_page_template', '13', null, null, null, null, null, 'false', 'true', 'false'),
  ('default_unit_page_template', '14', null, null, null, null, null, 'false', 'true', 'false');

-- Create table to hold default permissions for course pages

CREATE TABLE course_builder_config
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT,
    permission INT,
    page VARCHAR(255)
);

-- Default all permissions to none

INSERT INTO course_builder_config (role_id, permission, page) VALUES (1, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (2, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (3, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (4, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (5, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (6, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (7, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (8, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (9, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (10, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (11, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (12, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (13, 0, 'course');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (14, 0, 'course');

INSERT INTO course_builder_config (role_id, permission, page) VALUES (1, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (2, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (3, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (4, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (5, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (6, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (7, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (8, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (9, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (10, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (11, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (12, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (13, 0, 'unit');
INSERT INTO course_builder_config (role_id, permission, page) VALUES (14, 0, 'unit');