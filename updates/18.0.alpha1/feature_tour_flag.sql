INSERT INTO lu_config_type VALUES(NULL, "Feature Tours");

INSERT INTO `config`
VALUES (NULL, 'enable_feature_tours', 0, 'Enable Feature Tours', 'Allows configuration of feature tours.', 3, NULL,
(SELECT `id` FROM lu_config_type where type = 'Feature Tours'), 'true', 'false', 'true');
