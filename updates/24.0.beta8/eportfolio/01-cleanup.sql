DROP TABLE eportfolio_component_blog_image_backup;

DELETE FROM eportfolio_component_textbox_item
WHERE component_instance_id NOT IN (SELECT id FROM component_instance);

DELETE FROM eportfolio_component_linklist_item
WHERE component_instance_id NOT IN (SELECT id FROM component_instance);

DELETE FROM eportfolio_component_basiclist_item
WHERE component_instance_id NOT IN (SELECT id FROM component_instance);

DELETE FROM eportfolio_component_advancedlist_item
WHERE component_instance_id NOT IN (SELECT id FROM component_instance);
