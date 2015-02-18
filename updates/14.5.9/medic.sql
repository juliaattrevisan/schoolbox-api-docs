INSERT INTO config
(
  SELECT NULL, 'protect_medicalspecial_profile', 0, 'Hide Medical and Special Needs on Profiles', 'Tick this box to hide all Medical and Special Needs information from displaying on the Profile pages', field_id, `options`, type_id, editable, hidden, required
  FROM config
  WHERE `name` = 'protect_medicalspecial'
)
ON DUPLICATE KEY UPDATE `name` = 'protect_medicalspecial_profile';