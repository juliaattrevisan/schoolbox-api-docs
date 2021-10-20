-- Backup condition/effect tables
CREATE TABLE IF NOT EXISTS _ups_condition LIKE ups_condition;
INSERT INTO _ups_condition SELECT * FROM ups_condition;

CREATE TABLE IF NOT EXISTS _ups_effect LIKE ups_effect;
INSERT INTO _ups_effect SELECT * FROM ups_effect;
