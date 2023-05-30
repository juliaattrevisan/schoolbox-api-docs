UPDATE component_definition
SET
    class = 'Component_Dashboard_SlideshowController'
WHERE
    class = 'Component_Dashboard_ScrollerController';

UPDATE component_definition
SET
    class = 'Component_Homepage_SlideshowController'
WHERE
    class = 'Component_Homepage_ScrollerController';
