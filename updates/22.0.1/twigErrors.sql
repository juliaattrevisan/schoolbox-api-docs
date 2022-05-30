#some prod serves are displaying throwing twig errors, stop that
UPDATE config SET value = '0' WHERE name = 'strict_templates';
