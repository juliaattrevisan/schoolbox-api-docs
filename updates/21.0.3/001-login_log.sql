-- SA-11310
-- update the login_log table to support well-formed ipv6 addresses

alter table login_log modify ip varchar(39) collate utf8mb4_general_ci null comment 'coverage for both ipv4 and ipv6 addresses';
