-- Initially set all fields to accept all mime types
UPDATE `config` SET `options` = '{"mimeFilter": "*/*"}';

-- Now, restrict according to the old logic (_logo and _bg fields only accept images)
UPDATE `config`
SET
    `options` = '{"mimeFilter": "image/*"}'
WHERE
    `name` LIKE '%_logo%'
    OR `name` LIKE '%_bg%';

-- 4 fields need to be restricted to accept only images, these are:
--   email_banner_image     (currently */*)
--   skin_favicon           (currently */*)
--   saml_login_icon        (currently */*)
--   oidc_login_icon        (currently */*)

UPDATE `config`
SET
    `options` = '{"mimeFilter": "image/*"}'
WHERE
    `name` = 'email_banner_image';

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
