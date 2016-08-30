INSERT INTO schoolbox.config
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
  'protect_teacher_photo',
  '0',
  'Teacher Photo Visibility',
  'Select who can see the staff profile photos',
  7,
  '0;Everyone:1;Staff Only:2;Staff and Parents',
  5,
  'true',
  'false',
  'true'
);