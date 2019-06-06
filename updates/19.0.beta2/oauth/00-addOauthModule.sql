ALTER TABLE config MODIFY module ENUM('settings', 'comms', 'custom', 'oauth') DEFAULT 'settings' NULL;
