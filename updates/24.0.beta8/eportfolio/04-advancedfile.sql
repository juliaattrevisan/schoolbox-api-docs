UPDATE eportfolio_component_advancedlist_item SET imagehash = NULL WHERE imagehash NOT IN(SELECT hash FROM storage);


alter table eportfolio_component_advancedlist_item
    add constraint eportfolio_component_advancedlist_item_storage_hash_fk
        foreign key (imagehash) references storage (hash);
