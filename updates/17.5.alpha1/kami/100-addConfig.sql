-- Add the shared Kami API key placeholder
-- Intentionally not populated with the key, it will be filled by puppet for
-- those schools that are eligible for Kami, and who haven't opted out.
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required)
VALUES (
  'kami_api_key',
  NULL,
  'Kami API Key',
  'Kami API key for inline grading. Used to download and annotate submissions.',
  1,
  NULL,
  10,
  'false',
  'true',
  'false'
);


