### create new canvas area
INSERT INTO lu_config_type (type, sequence)
VALUES ('Canvas', 23);

### move old canvas configs to new area
update config set type_id = (
    SELECT id
    FROM lu_config_type
    WHERE
        type = 'Canvas'
) where name like 'canvas_%';
