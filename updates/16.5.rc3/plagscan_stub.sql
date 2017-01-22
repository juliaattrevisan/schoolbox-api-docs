-- Allow plagscan stub records to spawn
ALTER TABLE `plagscan_document` CHANGE `plagscan_id` `plagscan_id` INT(11) NULL;
