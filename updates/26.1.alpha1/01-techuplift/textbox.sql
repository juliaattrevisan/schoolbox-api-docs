alter table textBox
    change textBoxID id int auto_increment;

alter table textBox
    change categoryID folder_id int(11) unsigned null;

rename table textBox to textbox;
