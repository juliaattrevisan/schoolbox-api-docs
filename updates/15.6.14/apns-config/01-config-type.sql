UPDATE config
SET field_id = (
  SELECT id FROM lu_config_field WHERE `field` = 'file'
)
WHERE NAME = 'push_apns_cert_path';
