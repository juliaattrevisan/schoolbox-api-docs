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
