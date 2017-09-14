-- Temporarily null this so we can update the FK
UPDATE `component_definition`
SET `msg_namespace_slug`= NULL
WHERE `class`='Schoolbox\\Blog\\Homepage\\Component\\Controller';

-- Update the slug
UPDATE `msg_namespace`
SET `slug`='homepage_blog', label = 'Homepage Blog'
WHERE `slug`='blog';

-- And relink the component
UPDATE `component_definition`
SET `msg_namespace_slug`= 'homepage_blog'
WHERE `class`='Schoolbox\\Blog\\Homepage\\Component\\Controller';
