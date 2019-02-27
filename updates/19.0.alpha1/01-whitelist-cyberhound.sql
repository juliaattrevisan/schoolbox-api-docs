INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module) 
VALUES (  
  'cyberhound_whitelist', 
  '192.168.0.0/16
172.16.0.0/12
10.0.0.0/8', 
  'Cyberhound Whitelist', 
  'List of subnets that will be forwarded to Cyberhound Whitelist API. These should be only networks that are behind your firewall.  The Cyberhound API will not work for external users.',
  2, 
  '', 
  (SELECT id from lu_config_type WHERE type like '3rd Party Services'),
  'true', 
  'false', 
  'false', 
  'settings' 
);