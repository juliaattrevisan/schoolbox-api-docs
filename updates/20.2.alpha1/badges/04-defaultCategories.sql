SELECT id INTO @adminId FROM user WHERE username = 'alaress';

INSERT INTO badge_category (sequence, name,created_by, created_at, updated_at) VALUES
                                                              (1, 'Learning', @adminId, NOW(), NOW()),
                                                              (2,'Personal Development', @adminId, NOW(), NOW()),
                                                              (3, 'Community', @adminId, NOW(), NOW()),
                                                              (4, 'Extra-Curricular', @adminId, NOW(), NOW());
