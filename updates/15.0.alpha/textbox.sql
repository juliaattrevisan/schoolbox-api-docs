/* Make textboxes searchable
 */
ALTER TABLE `textBox` ADD FULLTEXT INDEX `fulltext` (`heading`, `body`);