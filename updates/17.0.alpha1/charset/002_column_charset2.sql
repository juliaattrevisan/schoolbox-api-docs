# ALTER TABLE blog_image MODIFY hash varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE blog_image MODIFY hash varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE calendar_connector MODIFY config text CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE calendar_connector MODIFY config text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE curriculum_learning_area MODIFY description text CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE curriculum_learning_area MODIFY description text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE curriculum_learning_level MODIFY description text CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE curriculum_learning_level MODIFY description text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE curriculum_node MODIFY description text CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE curriculum_node MODIFY description text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE curriculum_node MODIFY reference varchar(2048) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE curriculum_node MODIFY reference varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE dropbox MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE dropbox MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE eportfolio_component_advancedlist_item MODIFY imagehash varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE eportfolio_component_advancedlist_item MODIFY imagehash varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE eportfolio_component_blog_image MODIFY hash varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE eportfolio_component_blog_image MODIFY hash varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE eportfolio_coverphoto MODIFY imagehash varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE eportfolio_coverphoto MODIFY imagehash varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE file MODIFY hash varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE file MODIFY hash varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE folder MODIFY colour varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE folder MODIFY colour varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE folder MODIFY image_hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE folder MODIFY image_hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE mobile_app_registry MODIFY app_token varchar(4096) CHARACTER SET latin1 COLLATE latin1_general_ci;
ALTER TABLE mobile_app_registry MODIFY app_token varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE mobile_app_registry MODIFY device_id varchar(256) CHARACTER SET latin1 COLLATE latin1_general_ci;
ALTER TABLE mobile_app_registry MODIFY device_id varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE myFile MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE myFile MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE news_items MODIFY item_image varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE news_items MODIFY item_image varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE pastoral_attachment MODIFY hash varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE pastoral_attachment MODIFY hash varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE pastoral_tags_deprecated MODIFY name varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE pastoral_tags_deprecated MODIFY name varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE pastoral_upload_queue MODIFY hash varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE pastoral_upload_queue MODIFY hash varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE resource MODIFY image_hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE resource MODIFY image_hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE scheduleq MODIFY command varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE scheduleq MODIFY command varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE scheduleq MODIFY id_hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE scheduleq MODIFY id_hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE socialstream_post MODIFY cache_hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE socialstream_post MODIFY cache_hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE sportsfixtures_import_errors MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE sportsfixtures_import_errors MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE storage MODIFY hash varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE storage MODIFY hash varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE student_submission_file MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE student_submission_file MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE submission_box_file MODIFY storage_id varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE submission_box_file MODIFY storage_id varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE submission_return_file MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE submission_return_file MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE tile MODIFY background_image_hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE tile MODIFY background_image_hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE ups_condition MODIFY arguments varchar(4096) CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE ups_condition MODIFY arguments varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE ups_custom_hydrator MODIFY class varchar(256) CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE ups_custom_hydrator MODIFY class varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE ups_effect MODIFY arguments varchar(4096) CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE ups_effect MODIFY arguments varchar(4096) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE ups_log MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_bin;
ALTER TABLE ups_log MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE user MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE user MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE user MODIFY position_title varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE user MODIFY position_title varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE user_assume_log MODIFY ip varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE user_assume_log MODIFY ip varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE user_password_reset_request MODIFY ip varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE user_password_reset_request MODIFY ip varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE user_password_reset_request MODIFY token varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci;
ALTER TABLE user_password_reset_request MODIFY token varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# ALTER TABLE video_queue MODIFY hash varchar(40) CHARACTER SET utf8 COLLATE utf8_general_ci;
ALTER TABLE video_queue MODIFY hash varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

