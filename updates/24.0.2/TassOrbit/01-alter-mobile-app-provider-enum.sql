ALTER TABLE mobile_app
        MODIFY `provider` ENUM ('APNS', 'GCM', 'Digistorm', 'Firebase', 'TassOrbit') CHARSET 'ascii' NOT NULL;
