-- Set the mimeFilter for each field as it is currently handled:
UPDATE `config` co
JOIN `lu_config_field` lu ON co.field_id = lu.id
SET
    co.`options` = '{"mimeFilter": "*/*"}'
WHERE
    lu.field IN ('file', 'fileMulti');

UPDATE `config`
SET
    `options` = '{"mimeFilter": "image/*"}'
WHERE
    `name` = 'skin_logo_square'
    OR `name` = 'skin_logo_large'
    OR `name` = 'skin_logo_login'
    OR `name` = 'skin_login_bg';

-- 4 fields need to be restricted to accept only images, where previously they were not, these are:
--   email_banner_image     (currently */*)
--   skin_favicon           (currently */*)
--   saml_login_icon        (currently */*)
--   oidc_login_icon        (currently */*)

UPDATE `config`
SET
    `options` = '{"mimeFilter": "image/*"}'
WHERE
    `name` = 'email_banner_image'
    OR `name` = 'skin_favicon'
    OR `name` = 'saml_login_icon'
    OR `name` = 'oidc_login_icon';

-- They are now:
--   push_apns_cert_path    */*
--   skin_logo_square       image/*
--   skin_logo_large        image/*
--   skin_logo_login        image/*
--   ldap_ca_certificate    */*
--   email_banner_image     image/*
--   skin_favicon           image/*
--   saml_login_icon        image/*
--   oidc_login_icon        image/*
--   skin_login_bg          image/*
