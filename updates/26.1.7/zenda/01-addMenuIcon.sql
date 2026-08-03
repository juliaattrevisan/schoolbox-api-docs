INSERT INTO
    menu_icons (name, title)
VALUES ('zenda', 'Zenda')
ON DUPLICATE KEY UPDATE
    title = VALUES(title);
