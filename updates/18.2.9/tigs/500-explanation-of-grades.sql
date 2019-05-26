-- Inserts a new config to identity the 'Explanation of Grades' textbox
INSERT INTO config (
  name, value, title, description, field_id, options, type_id, editable, hidden, required, module
) VALUES (
    'tigs_crp_textbox_heading_explanation_of_grades',
    'Explanation of Grades',
     '\'Explanation of Grades\' textbox heading',
    'System will treat the textbox with this heading as a \'Explanation of Grades\'',
    (SELECT lcf.id FROM lu_config_field lcf WHERE lcf.field = 'input'),
    null,
    null,
    'false',
    'false',
    'true',
    'custom'
);
