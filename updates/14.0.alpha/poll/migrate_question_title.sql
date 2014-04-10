-- Add a new config setting for alignment
INSERT INTO component_config_definition (
  component_definition_id,
  `key`,
  label,
  default_value
)
  VALUES (
    (SELECT cd.id FROM component_definition cd WHERE cd.class='Component_Homepage_PollController' LIMIT 1),
    'componentname',
    'Heading',
    'Poll'
  );


ALTER TABLE `poll` DROP COLUMN `title`;

