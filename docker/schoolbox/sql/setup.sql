DROP DATABASE IF EXISTS schoolbox;
CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

# for now need to create vagrant user
DROP USER IF EXISTS 'schoolboxvagrant'@'localhost';
CREATE USER IF NOT EXISTS 'schoolboxvagrant'@'localhost' IDENTIFIED BY 'abc123';

# create Docker users
DROP USER IF EXISTS 'schoolboxdocker'@'%';
CREATE USER IF NOT EXISTS 'schoolboxdocker'@'%' IDENTIFIED BY 'abc123';

# grant everything to all these users
GRANT ALL ON schoolbox.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON schoolbox.* to 'schoolboxdocker'@'%';
