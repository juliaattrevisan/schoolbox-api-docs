alter table eportfolio_component_textbox_item
    modify component_instance_id int UNSIGNED not null;

alter table eportfolio_component_linklist_item
    modify component_instance_id int UNSIGNED not null;

alter table eportfolio_component_basiclist_item
    modify component_instance_id int UNSIGNED not null;

alter table eportfolio_component_advancedlist_item
    modify component_instance_id int UNSIGNED not null;
