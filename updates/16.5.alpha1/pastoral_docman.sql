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
  'external_pastoral_upload',
  '0',
  'External Pastoral Attachment Upload',
  'Enables saving of pastoral attachments to external SIS (Synergetic Only).',
  3,
  '',
  7,
  'true',
  'false',
  'false'
);

CREATE TABLE pastoral_upload_queue
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  pastoral_id INT,
  student_external_id INT,
  external_id INT,
  hash VARCHAR(255),
  type VARCHAR(255),
  status VARCHAR(255),
  message TEXT,
  created_at DATETIME,
  updated_at DATETIME,
  deleted_at DATETIME
);

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
  'pastoral_processing_timeout',
  '3600',
  'Pastoral Care External SIS Upload Processing Timeout',
  'The maximum number of seconds that the system will attempt to upload a pastoral attachment to the SIS before timing out and being marked as failed. (Set to 0 or less to disable timeout.)',
  1,
  null,
  4,
  'true',
  'false',
  'false'
);