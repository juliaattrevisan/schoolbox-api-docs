DROP DATABASE IF EXISTS schoolbox;
CREATE DATABASE schoolbox CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

DROP DATABASE IF EXISTS schoolbox2;
CREATE DATABASE schoolbox2 CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

# for now need to create vagrant user
DROP USER IF EXISTS 'schoolboxvagrant'@'localhost';
CREATE USER IF NOT EXISTS 'schoolboxvagrant'@'localhost' IDENTIFIED BY 'abc123';

# create Docker users
DROP USER IF EXISTS 'schoolboxdocker'@'%';
CREATE USER IF NOT EXISTS 'schoolboxdocker'@'%' IDENTIFIED BY 'abc123';

# grant everything to all these users
GRANT ALL ON schoolbox.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON schoolbox.* to 'schoolboxdocker'@'%';
GRANT ALL ON schoolbox2.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON schoolbox2.* to 'schoolboxdocker'@'%';

# canvas db rather than fighting tools/canvas/create_database.php
# drop db if it alredy exists
DROP DATABASE IF EXISTS canvas_data_docker;
DROP DATABASE IF EXISTS canvas_data2_docker;
DROP DATABASE IF EXISTS canvas_data_docker2;
DROP DATABASE IF EXISTS canvas_data2_docker2;

# now create it again
CREATE DATABASE canvas_data_docker CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
CREATE DATABASE canvas_data2_docker CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
CREATE DATABASE canvas_data_docker2 CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';
CREATE DATABASE canvas_data2_docker2 CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

# grant everything to all these users
GRANT ALL ON canvas_data_docker.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON canvas_data_docker.* to 'schoolboxdocker'@'%';
GRANT ALL ON canvas_data_docker2.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON canvas_data_docker2.* to 'schoolboxdocker'@'%';
GRANT ALL ON canvas_data2_docker.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON canvas_data2_docker.* to 'schoolboxdocker'@'%';
GRANT ALL ON canvas_data2_docker2.* to 'schoolboxvagrant'@'localhost';
GRANT ALL ON canvas_data2_docker2.* to 'schoolboxdocker'@'%';

# grant file import privileges to these users (canvas uploads its data this way)
GRANT file ON *.* TO 'schoolboxvagrant'@'localhost';
GRANT file ON *.* TO 'schoolboxdocker'@'%';
