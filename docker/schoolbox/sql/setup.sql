DROP DATABASE IF EXISTS schoolbox;
CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

# for now need to create vagrant user
CREATE USER IF NOT EXISTS 'schoolboxvagrant'@'localhost' IDENTIFIED BY 'abc123';

# create Docker users
CREATE USER IF NOT EXISTS 'schoolboxdocker'@'php-fpm' IDENTIFIED BY 'abc123';
CREATE USER IF NOT EXISTS 'schoolboxdocker'@'schoolbox-cli' IDENTIFIED BY 'abc123';

# grant everything to all these users
GRANT ALL ON schoolbox.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON schoolbox.* to 'schoolboxdocker'@'php-fpm';
GRANT ALL ON schoolbox.* to 'schoolboxdocker'@'schoolbox-cli';
