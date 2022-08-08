-- given_name virtual column: for ease of requesting preferred then first name
ALTER TABLE user
ADD given_name VARCHAR(255) AS (IF(COALESCE(preferred_name, '') = '', firstname, preferred_name)) VIRTUAL
AFTER preferred_name;
