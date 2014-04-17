/* Allow visibility settings on Quiz review screens etc
 */
ALTER TABLE `assess` ADD COLUMN `visibility` INT(1) DEFAULT 1 NULL AFTER `notify_author`;

CREATE TABLE `assess_visibility`( `id` INT(11) NOT NULL, `name` VARCHAR(255), PRIMARY KEY (`id`) );
INSERT INTO `assess_visibility` (`id`, `name`) VALUES (0, 'Staff Only');
INSERT INTO `assess_visibility` (`id`, `name`) VALUES (1, 'Staff and Students');
INSERT INTO `assess_visibility` (`id`, `name`) VALUES (2, 'Staff, Students and Parents');