
/* Error messages */
INSERT INTO error (id, message) VALUES (138, 'This username already exists.');
INSERT INTO error (id, message) VALUES (139, 'The link provided is not a correctly formatted URL.');
INSERT INTO error (id, message) VALUES (140, 'You already have that link in your list.');

/* Insert SOAP API config variables */
INSERT INTO config (`name`, `title`, `description`, `field`, `type`, `editable`) VALUES
('external_web_services_url', 'SOAP Web Services URL', 'URL of the web services API that can handle SOAP requests', 'input', 'External DB', 'true');
INSERT INTO config (`name`, `title`, `description`, `field`, `type`, `editable`) VALUES
('external_vendor_token_guid', 'Synergetic Vendor Token GUID', 'The Vendor Token GUID used to access your Synergetic SOAP API', 'input', 'External DB', 'true');
INSERT INTO config (`name`, `title`, `description`, `field`, `type`, `editable`) VALUES
('external_vendor_password', 'Synergetic Vendor Password', 'The Vendor Password used to access your Synergetic SOAP API', 'input', 'External DB', 'true');
