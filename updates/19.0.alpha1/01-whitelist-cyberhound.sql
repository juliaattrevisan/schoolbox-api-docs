INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module) 
VALUES (  
  'cyberhound_whitelist', 
  '192.168.0.0/16
172.16.0.0/12
10.0.0.0/8', 
  'Cyberhound Whitelist', 
  '', 
  2, 
  '', 
  (SELECT id from lu_config_type WHERE name like '3rd Party Services'),
  'true', 
  'false', 
  'false', 
  'settings' 
);