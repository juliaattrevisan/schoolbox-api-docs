### cache the school code
SET
@canvas_config_id = (
        SELECT id
        FROM lu_config_type
        WHERE
            type = 'Canvas'
    );