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
    module
) VALUES (
             'web_crawlers_enabled',
             (SELECT if(value='prod', '1', '0') from config where name = 'site_type'),
             'Allow Web Crawlers',
             'When set to ‘ON’, search engine web crawlers will be able to indexing the site',
             (SELECT id FROM lu_config_field WHERE field = 'boolean'),
             '',
             (SELECT id FROM lu_config_type WHERE type = 'Modules'),
             'true',
             'false',
             'false',
             'settings'
         );
