-- Prior to this update, various URLs in the DB have different lengths. Ranging from 250 to 2048 varchar.
-- This script standardises all URLs at 2048 char.
-- This length is chosen as it's the max length for Google static URLs


-- Some config definitions are URLs. They store their value in this column.
-- Although this column is also used for all other config definitions too.
ALTER TABLE `component_config_instance` MODIFY `value`     VARCHAR(2048);

-- Not used by the mapper and I'm unable to find usage in code base. Deprecated?
ALTER TABLE `resource`                  MODIFY `image_url` VARCHAR(2048);

-- Various previous values from 250 to 2000 char.
ALTER TABLE `bookmarks`                 MODIFY `url`       VARCHAR(2048);
ALTER TABLE `curriculum_learning_area`  MODIFY `reference` VARCHAR(2048);
ALTER TABLE `curriculum_learning_level` MODIFY `reference` VARCHAR(2048);
ALTER TABLE `curriculum_node`           MODIFY `reference` VARCHAR(2048);
ALTER TABLE `eportfolio_link`           MODIFY `url`       VARCHAR(2048);
ALTER TABLE `socialstream_post`         MODIFY `thumbnail` VARCHAR(2048);
ALTER TABLE `socialstream_post`         MODIFY `url`       VARCHAR(2048);
ALTER TABLE `task`                      MODIFY `link`      VARCHAR(2048);
ALTER TABLE `task_instance`             MODIFY `link`      VARCHAR(2048);
ALTER TABLE `tile`                      MODIFY `link`      VARCHAR(2048);