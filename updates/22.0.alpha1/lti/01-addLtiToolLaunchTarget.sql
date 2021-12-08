ALTER TABLE lti_provider
ADD COLUMN target ENUM('iframe', 'window') NOT NULL DEFAULT 'iframe' AFTER custom;
