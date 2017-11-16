-- Add a feature flag to enable export of an already-imported course assessment to clobber the existing imported assessment.
INSERT INTO config (
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
  'destructive_activity_export_enabled',
  '0',
  'Enable destructive course activity export',
  'If enabled, exporting a course activity to classes already containing the activity will cause the class activity to be deleted and re-imported. Any student submissions or teacher feedback will be deleted.',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  '',
  (SELECT id FROM lu_config_type WHERE type = 'LMS Policy'),
  'true',
  'false',
  'true'
);

