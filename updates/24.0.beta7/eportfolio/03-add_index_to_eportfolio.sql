alter table eportfolio_component_textbox_item
    add constraint eportfolio_component_textbox_item_component_instance_fk
        foreign key (component_instance_id) references component_instance (id);

alter table eportfolio_component_linklist_item
    add constraint eportfolio_component_linklist_item_component_instance_fk
        foreign key (component_instance_id) references component_instance (id);


alter table eportfolio_component_basiclist_item
    add constraint eportfolio_component_basiclist_item_component_instance_fk
        foreign key (component_instance_id) references component_instance (id);

alter table eportfolio_component_advancedlist_item
    add constraint eportfolio_component_advancedlist_item_component_instance_fk
        foreign key (component_instance_id) references component_instance (id);
