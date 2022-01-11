INSERT INTO component_definition (name, class, description, module, component_category, enabled, visible,
                                            multiple, msg_namespace_slug, msg_namespace_slug_suffix, config_copy)
VALUES ('Canvas Assignments', 'Component_Dashboard_CanvasAssignmentsController', 'Canvas Assignments', 'dashboard',
        null, (SELECT COUNT(*) FROM config WHERE value = 'NEWIC'), 'true', 'false', null, null, null);



-- push everything down

UPDATE config
SET sequence = sequence+1
WHERE sequence > (SELECT sequence FROM config WHERE name = 'canvas_data_api_secret');

#grab from steve ce5c867d85abb3fee672d663a652f2595722f110 [SA-12825]
#we both need the `canvas_bearer_token`
-- squeeze into the gap

INSERT INTO `config`
(
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `hidden`,
    `required`,
    `module`,
    `sequence`
)
VALUES (
           'canvas_bearer_token',
           '',
           'Canvas Bearer Token',
           'The authorization bearer token for importing Canvas Course Announcements and Canvas Assignments component',
           '1',
           '7',
           'true',
           'true',
           'false',
           'settings',
           (SELECT sequence+1 FROM config WHERE name = 'canvas_data_api_secret')
       );
