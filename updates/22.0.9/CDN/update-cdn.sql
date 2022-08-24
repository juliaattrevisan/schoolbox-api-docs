UPDATE `config`
SET `config`.`value` = 'https://schoolbox-static.cloud/{site_package}'
WHERE `config`.`name` = 'site_static_asset_prefix';

UPDATE `config`
SET `config`.`value` = IF((SELECT `config`.`value`
                           FROM `config`
                           WHERE `config`.`name` = 'site_environment')
                              = 'client-hosted', '0', '1')
WHERE `config`.`name` = 'site_static_asset_serve_from_cdn';
