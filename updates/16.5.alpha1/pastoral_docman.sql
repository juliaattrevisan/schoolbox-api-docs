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
  'external_pastoral_docman',
  '0',
  'External Pastoral DocMan Writeback',
  'Enables saving of pastoral attachments to Synergetic DocMan.',
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
  hash VARCHAR(255),
  type VARCHAR(255),
  status VARCHAR(255),
  created_at DATETIME,
  updated_at DATETIME,
  deleted_at DATETIME
);