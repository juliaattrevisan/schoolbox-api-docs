-- Extended based on recommendation in https://secure.php.net/manual/en/function.password-hash.php
ALTER TABLE `user` CHANGE `password` `password` VARCHAR(255) CHARSET utf8 COLLATE utf8_general_ci NULL;
