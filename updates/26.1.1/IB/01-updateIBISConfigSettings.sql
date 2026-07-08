-- Updating IBIS config settings to make sure they have the missing settings filled
UPDATE config
SET description = '',
    editable = 'false',
    hidden = 'true',
    required = 'false'

WHERE name in ('ibis_secret_key', 'ibis_app_id', 'ibis_endpoint');
