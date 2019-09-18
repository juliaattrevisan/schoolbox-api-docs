-- Support can configure the Spouse Prefix for TASS users
-- Default is 'S'
INSERT INTO `config` (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES
('external_tass_spouse_prefix', 'S', 'TASS Spouse Prefix', 'Prefix for a spouse external id', 1, null, 7, 'false', 'true', 'true', 'settings');
