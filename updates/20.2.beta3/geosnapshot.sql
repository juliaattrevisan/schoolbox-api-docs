INSERT INTO component_definition (
    name,
    class,
    description,
    module,
    component_category,
    visible,
    multiple,
    msg_namespace_slug,
    msg_namespace_slug_suffix,
    config_copy
) VALUES (
             'Geoshapshot',
             'Schoolbox\\Geosnapshot\\Component\\Dashboard\\Controller',
             'Displays a Geosnapshot event gallery on your dashboard, ensure you have configured your Geosnapshot school code before using this component.',
             'dashboard',
             null,
             'true',
             'false',
             null,
             null,
             null
         );

INSERT INTO config(name, value, title, description, field_id, options, type_id, editable, hidden, required, module, sequence)
VALUES (
           'geosnapshot_school_code',
           '',
           'Geosnapshot Organisation Code',
           'Input your Geosnapshot organisation code to allow integrations with Geosnapshot to be linked with your school',
           (SELECT id FROM lu_config_field WHERE field = 'input'),
           '',
           (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'),
           'true',
           'false',
           'false',
           'settings',
           (SELECT MAX(sequence) + 1 FROM config c WHERE `module` = 'settings' AND type_id = (SELECT id FROM lu_config_type WHERE `type` = '3rd Party Services'))
       );
