DELETE eportfolio_permission FROM eportfolio_permission
  LEFT JOIN eportfolio_page ON eportfolio_page.page_id = eportfolio_permission.page_id
WHERE eportfolio_page.page_id IS NULL;

ALTER TABLE `eportfolio_permission`
  ADD INDEX `fk_eportfolio_permission_page_id_idx` (`page_id` ASC);
ALTER TABLE `eportfolio_permission`
  ADD CONSTRAINT `fk_eportfolio_permission_page_id`
FOREIGN KEY (`page_id`)
REFERENCES `eportfolio_page` (`page_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
