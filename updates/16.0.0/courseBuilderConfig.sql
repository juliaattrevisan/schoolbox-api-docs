-- Add config variables that hold default course page templates

INSERT INTO schoolbox.config
  (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES
  ('default_course_page_template', '13', null, null, null, null, null, 'false', 'true', 'false'),
  ('default_unit_page_template', '14', null, null, null, null, null, 'false', 'true', 'false');

-- Create table to hold default permissions for course pages

CREATE TABLE schoolbox.course_builder_config
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT,
    permission INT,
    page VARCHAR(255)
);

-- Default all permissions to none

INSERT INTO schoolbox.course_builder_config
  (role_id, permission, page)
VALUES
  (1, 0, 'course'),
  (2, 0, 'course'),
  (3, 0, 'course'),
  (4, 0, 'course'),
  (5, 0, 'course'),
  (6, 0, 'course'),
  (7, 0, 'course'),
  (8, 0, 'course'),
  (9, 0, 'course'),
  (10, 0, 'course'),
  (11, 0, 'course'),
  (12, 0, 'course'),
  (13, 0, 'course'),
  (14, 0, 'course'),
  (1, 0, 'unit'),
  (2, 0, 'unit'),
  (3, 0, 'unit'),
  (4, 0, 'unit'),
  (5, 0, 'unit'),
  (6, 0, 'unit'),
  (7, 0, 'unit'),
  (8, 0, 'unit'),
  (9, 0, 'unit'),
  (10, 0, 'unit'),
  (11, 0, 'unit'),
  (12, 0, 'unit'),
  (13, 0, 'unit'),
  (14, 0, 'unit');