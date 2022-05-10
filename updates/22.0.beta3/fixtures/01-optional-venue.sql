ALTER TABLE `sportsfixtures_match` 
DROP FOREIGN KEY `sportsfixtures_match_ibfk_3`;
ALTER TABLE `sportsfixtures_match` 
CHANGE COLUMN `venue_id` `venue_id` INT(11) NULL DEFAULT NULL ;
ALTER TABLE `sportsfixtures_match` 
ADD CONSTRAINT `sportsfixtures_match_ibfk_3`
  FOREIGN KEY (`venue_id`)
  REFERENCES `sportsfixtures_venue` (`id`);
