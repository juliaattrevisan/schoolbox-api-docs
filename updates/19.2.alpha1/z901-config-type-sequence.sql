alter table lu_config_type
    add sequence int null;

UPDATE lu_config_type t SET t.sequence = 15 WHERE t.id = 12;
UPDATE lu_config_type t SET t.sequence = 0 WHERE t.id = 13;
UPDATE lu_config_type t SET t.sequence = 20 WHERE t.id = 14;
UPDATE lu_config_type t SET t.sequence = 9 WHERE t.id = 6;
UPDATE lu_config_type t SET t.sequence = 5 WHERE t.id = 3;
UPDATE lu_config_type t SET t.sequence = 3 WHERE t.id = 21;
UPDATE lu_config_type t SET t.sequence = 16 WHERE t.id = 17;
UPDATE lu_config_type t SET t.sequence = 10 WHERE t.id = 7;
UPDATE lu_config_type t SET t.sequence = 6 WHERE t.id = 4;
UPDATE lu_config_type t SET t.sequence = 17 WHERE t.id = 18;
UPDATE lu_config_type t SET t.sequence = 11 WHERE t.id = 8;
UPDATE lu_config_type t SET t.sequence = 7 WHERE t.id = 5;
UPDATE lu_config_type t SET t.sequence = 4 WHERE t.id = 16;
UPDATE lu_config_type t SET t.sequence = 12 WHERE t.id = 9;
UPDATE lu_config_type t SET t.sequence = 1 WHERE t.id = 1;
UPDATE lu_config_type t SET t.sequence = 18 WHERE t.id = 19;
UPDATE lu_config_type t SET t.sequence = 13 WHERE t.id = 15;
UPDATE lu_config_type t SET t.sequence = 19 WHERE t.id = 20;
UPDATE lu_config_type t SET t.sequence = 2 WHERE t.id = 2;
UPDATE lu_config_type t SET t.sequence = 14 WHERE t.id = 11;
UPDATE lu_config_type t SET t.sequence = 8 WHERE t.id = 10;
