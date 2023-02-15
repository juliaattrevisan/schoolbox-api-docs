-- embiggen the maximum curric nname size for now
-- something is busted with their data
ALTER TABLE `curriculum_node`
    CHANGE COLUMN `name` `name` VARCHAR(8192) NULL DEFAULT NULL;

-- Add curric package option
INSERT INTO `package` (`title`, `description`, `location`, `installed`)
VALUES (
    'Western Australia Curriculum',
    'Attaches codes to the Western Australia Curriculum to allow for the mapping of these to learning resources.',
    'WACurriculum',
    '0'
);