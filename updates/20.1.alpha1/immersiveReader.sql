UPDATE config SET sequence = sequence+2 WHERE type_id = (SELECT id FROM lu_config_type WHERE type = '3rd Party Services') and sequence > 12;


# Setup location for Microsoft keys for
INSERT INTO config(
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
    sequence
) VALUES (
             'office365_tenant_id',
             '',
             'Microsoft Azure Tenant ID',
             'The Tenant ID for your Azure instance, this is required for Immersive Reader',
             (SELECT id FROM lu_config_field WHERE field = 'input'),
             '',
             (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
             'true',
             'false',
             'false',
              13
         ), (
             'office365_immersive_reader_name',
             '',
             'Microsoft Immersive Reader Name',
             'The name of the Immersive Reader service inside your Azure Tenancy',
             (SELECT id FROM lu_config_field WHERE field = 'input'),
             '',
             (SELECT id FROM lu_config_type WHERE type = '3rd Party Services'),
             'true',
             'false',
             'false',
             14
         );
