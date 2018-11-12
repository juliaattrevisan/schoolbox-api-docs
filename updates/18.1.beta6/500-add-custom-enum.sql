ALTER TABLE config
  CHANGE COLUMN module module ENUM ('settings', 'comms', 'custom') DEFAULT 'settings';
