ALTER TABLE user
ADD preferred_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL
AFTER firstname;
