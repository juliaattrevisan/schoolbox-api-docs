INSERT INTO config
(
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
)
VALUES
(
  'protect_student_contacts',
  '0',
  'Student Contact Visibility',
  'Select who can see student contacts on profile page',
  7,
  '0;Everyone:1;Staff Only:2;Staff and Parents',
  5,
  'true',
  'false',
  'true'
);