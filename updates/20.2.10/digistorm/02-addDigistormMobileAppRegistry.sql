ALTER TABLE mobile_app_registry
MODIFY provider ENUM('APNS', 'GCM', 'Digistorm') CHARSET ASCII NOT NULL;
