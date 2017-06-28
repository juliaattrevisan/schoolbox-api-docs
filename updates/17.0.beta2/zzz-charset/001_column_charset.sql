ALTER TABLE filter_default MODIFY role_type ENUM('staff', 'parent', 'student', 'guest') CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE filter_page MODIFY page ENUM('pending', 'grades', 'markbook', 'analytics', 'dueWorkCal') CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE filter_selection MODIFY page ENUM('pending', 'grades', 'markbook', 'analytics', 'dueWorkCal') CHARACTER SET ascii COLLATE ascii_general_ci;
ALTER TABLE lu_work_type MODIFY external_result_type VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
