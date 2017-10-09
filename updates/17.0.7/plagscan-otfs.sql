CREATE TABLE plagscan_document_migration
(
  plagscan_document_id INT PRIMARY KEY NOT NULL,
  storage_hash VARCHAR(40) CHARACTER SET ASCII NULL,
  storage_migrated_at DATETIME NULL,
  storage_failed_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL
);