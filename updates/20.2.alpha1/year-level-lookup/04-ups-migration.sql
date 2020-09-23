/* Make a backup of ups_effect.arguments into ups_effect._arguments
 */
ALTER TABLE `ups_effect`
    ADD COLUMN `_arguments` VARCHAR(4096) CHARACTER SET 'utf8mb4' NULL AFTER `arguments`;

UPDATE ups_effect
SET _arguments = arguments;
