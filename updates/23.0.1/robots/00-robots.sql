### add robot config to admin

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
    required,
    module,
    sequence
) VALUES (
             'web_crawlers_enabled',
             (SELECT if(cs.value='prod', '1', '0') from config cs where cs.name = 'site_type'),
             'Allow Web Crawlers',
             'When set to ‘ON’, search engine web crawlers will be able to index the site',
             (SELECT id FROM lu_config_field WHERE field = 'boolean'),
             '',
             (SELECT id FROM lu_config_type WHERE type = 'Site'),
             'true',
             'false',
             'false',
             'settings',
             (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = 'Site'))
         );
