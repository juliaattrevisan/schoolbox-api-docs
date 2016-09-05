INSERT INTO schoolbox.config (
  name,
  value,
  title,
  description,
  field_id,
  `options`,
  type_id,
  editable,
  hidden,
  required
) VALUES (
  'pastoral_external_delete',
  '1',
  'Delete pastoral care records from external SIS',
  'When deleting pastoral care records from Schoolbox the corresponding record will be deleted from the external SIS.',
  3,
  '',
  5,
  'true',
  'false',
  'false'
);