UPDATE `homepageComponentCategory` SET `name` = 'Multimedia' WHERE `homepageComponentCategoryID` = '4';

UPDATE `component_definition` SET `component_category` = '4' WHERE `class` = 'Component_Homepage_AudioController';
UPDATE `component_definition` SET `component_category` = '4' WHERE `class` = 'Component_Homepage_VideoController';
UPDATE `component_definition` SET `component_category` = '4' WHERE `class` = 'Component_Homepage_ClickviewController';
UPDATE `component_definition` SET `component_category` = '6' WHERE `class` = 'Component_Homepage_WolframController';