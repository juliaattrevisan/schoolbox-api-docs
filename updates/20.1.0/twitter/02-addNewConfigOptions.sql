INSERT IGNORE INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
)
SELECT cd.id, 'twitterListSlug', '', 'List Slug', 'The slug of the list (if any) to show in this component'
FROM component_definition cd
WHERE cd.class IN ('Schoolbox\\Twitter\\Component\\Homepage\\Controller', 'Schoolbox\\Twitter\\Component\\Dashboard\\Controller');

INSERT IGNORE INTO component_config_definition (
    component_definition_id,
    `key`,
    default_value,
    label,
    description
)
SELECT cd.id, 'twitterCollectionId', '', 'Collection ID', 'The ID of the collection (if any) to show in this component'
FROM component_definition cd
WHERE cd.class IN ('Schoolbox\\Twitter\\Component\\Homepage\\Controller', 'Schoolbox\\Twitter\\Component\\Dashboard\\Controller');
