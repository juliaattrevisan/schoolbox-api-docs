ALTER TABLE config
  CHANGE COLUMN module module ENUM ('settings', 'comms', 'custom') DEFAULT 'settings';

INSERT INTO config(name, value, title, description, field_id, editable, hidden, required, module) VALUES (
  'tigs_crp_participation_report_enabled',
  '0',
  'Participation Report enabled',
  'If enabled, students and parents can access the Participation Report. Staff can always access.',
  (SELECT id FROM lu_config_field WHERE field = 'boolean'),
  'true',
  'false',
  'true',
  'custom'
);
