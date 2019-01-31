INSERT INTO config(name, value, title, description, field_id, editable, hidden, required, module) VALUES (
  'tigs_crp_course_description_textbox_heading',
  'Course Description',
  'Course Textbox Heading',
  'Contents of a textbox on the course homepage with this heading will be rendered on the Subject Report',
  (SELECT id FROM lu_config_field WHERE field = 'input'),
  'false',
  'false',
  'true',
  'custom'
);
