-- Enables the class teacher to decide whether they wish to render closed assessments in homepage assessment components
-- Defaults to FALSE
-- Due Work
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES (
  (SELECT id FROM component_definition WHERE name = 'Due Work'),
  'showClosed',
  false,
  'Show Closed',
  'Whether to include closed due work in the list'
);
-- Quiz
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES (
  (SELECT id FROM component_definition WHERE name = 'Quizzes'),
  'showClosed',
  false,
  'Show Closed',
  'Whether to include closed quizzes in the list'
);
-- Task
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES (
  (SELECT id FROM component_definition WHERE name = 'Tasks'),
  'showClosed',
  false,
  'Show Closed',
  'Whether to include closed tasks in the list'
);
