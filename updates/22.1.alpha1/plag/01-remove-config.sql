-- Remove any plag-related configs

DELETE FROM `config`
WHERE NAME IN ('plagscan_api_key', 'plagscan_client_id', 'plagscan_username')
LIMIT 3;
