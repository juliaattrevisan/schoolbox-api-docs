ALTER TABLE mobile_app
MODIFY provider ENUM('APNS', 'GCM', 'Digistorm') CHARSET ASCII NOT NULL;
