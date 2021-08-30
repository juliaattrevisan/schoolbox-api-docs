-- Create a mapping between submission_box_id and group_id from Turnitin
-- group_id is a hyphen-separated alphanumeric e.g. "b5cf0e76-c1c7-11e8-a355-529269fb1459"
CREATE TABLE `turnitin_group` (
    `submission_box_id` INT NOT NULL,
    `group_id` VARCHAR(255) NOT NULL
);
