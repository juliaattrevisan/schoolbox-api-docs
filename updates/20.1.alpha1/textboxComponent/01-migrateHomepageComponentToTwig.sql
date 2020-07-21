/**
 * Move homepage textbox component to Twig
 */
UPDATE component_definition
SET    class = 'Schoolbox\\Resource\\Textbox\\Component\\Homepage\\Controller'
WHERE  class = 'Component_Homepage_TextBoxController';
