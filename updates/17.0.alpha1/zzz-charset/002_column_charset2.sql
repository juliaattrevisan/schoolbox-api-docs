-- Columns that were left out of last sweep
ALTER TABLE calendar_connector MODIFY config text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE curriculum_learning_area MODIFY description text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE curriculum_learning_level MODIFY description text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE curriculum_node MODIFY description text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE curriculum_node MODIFY reference varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE pastoral_tags_deprecated MODIFY name varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE scheduleq MODIFY command varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE scheduleq MODIFY id_hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE ups_condition MODIFY arguments varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE ups_effect MODIFY arguments varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE user MODIFY position_title varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- New columns added recently
ALTER TABLE filter_default MODIFY role_type varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE filter_option MODIFY `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE filter_option MODIFY page enum('pending','grades','markbook','analytics','dueWorkCal') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE filter_selection MODIFY `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE filter_selection MODIFY page enum('pending','grades','markbook','analytics','dueWorkCal') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE localisation MODIFY indefinite_article varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE localisation MODIFY title varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE pastoral_tag MODIFY name varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE plagscan_document MODIFY interactive_url varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
