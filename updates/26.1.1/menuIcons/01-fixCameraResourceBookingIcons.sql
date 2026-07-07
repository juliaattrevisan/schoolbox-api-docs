INSERT INTO
    menu_icons (name, title)
VALUES
    ('camera', 'Camera'),
    ('resource-booking', 'Resource Booking')
ON DUPLICATE KEY UPDATE
    title = VALUES(title);
