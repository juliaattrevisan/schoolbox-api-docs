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
  'pending_assessment_types',
  '0',
  'Assessments page filter',
  'Select which Activity types to display on the pending Assessments page',
  7,
  '0;DueWork + Quiz:1;DueWork + Quiz + Tasks',
  15,
  'true',
  'false',
  'true');