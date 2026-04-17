-- Create lu_nationality table to store the list of nationalities
CREATE TABLE `lu_nationality`
(
    `id`   INT(11) auto_increment PRIMARY KEY,
    `code` VARCHAR(3)   NOT NULL,
    `name` VARCHAR(255) NOT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `lu_nationality` (`code`, `name`)
VALUES ('AUS', 'Australian'),
       ('JAP', 'Japanese'),
       ('ESP', 'Spanish'),
       ('CHN', 'Chinese'),
       ('USA', 'American'),
       ('BRA', 'Brazilian');
