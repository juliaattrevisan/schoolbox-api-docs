/* Add ability for links to have a target */
ALTER TABLE `link`
ADD COLUMN `target` ENUM('','_blank') NULL AFTER `hidden`;