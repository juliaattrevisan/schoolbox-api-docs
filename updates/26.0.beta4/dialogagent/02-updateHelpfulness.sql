-- Update previous helpfulness values to new enum values
ALTER TABLE `dialog_agent`
    MODIFY COLUMN `helpfulness` VARCHAR(16) NULL;

UPDATE `dialog_agent`
SET `helpfulness` = CASE `helpfulness`
    WHEN 'minimal' THEN 'exploratory'
    WHEN 'moderate' THEN 'guided'
    WHEN 'extreme' THEN 'directed'
    ELSE NULL
END;

-- Update the enum now that all old values have been migrated
ALTER TABLE `dialog_agent`
    MODIFY COLUMN `helpfulness` ENUM('exploratory', 'guided', 'directed') NULL;
