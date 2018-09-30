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
  'student_teacher_class_visibility',
  '0',
  'Only show students class teachers',
  'When enabled, only show the teachers whose class a student is enrolled in when a folder contains multiple class codes. When disabled, they will see all teachers of the subject including those that do not teach them.',
  3,
  '',
  7,
  'true',
  'false',
  'false'
);