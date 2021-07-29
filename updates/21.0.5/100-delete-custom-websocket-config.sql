-- These are no longer supported as the instance cannot dictate cloud routing.
-- Websockets are now served over the same port as the site (normally 443) and always over HTTPS.
DELETE FROM config WHERE name IN ('websocket_path','websocket_port_ssl','websocket_port');
