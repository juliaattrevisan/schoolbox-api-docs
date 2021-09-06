-- This should match luTitle.titleName
-- All the columns in FULLTEXT KEY need to be updated together
ALTER TABLE `user`
    MODIFY `username` varchar(60) CHARACTER SET utf8mb4 DEFAULT NULL,
    MODIFY `title` varchar(20) CHARACTER SET utf8mb4 DEFAULT NULL,
    MODIFY `firstname` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
    MODIFY `lastname` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
    MODIFY `alt_email` varchar(60) CHARACTER SET utf8mb4 DEFAULT NULL;
