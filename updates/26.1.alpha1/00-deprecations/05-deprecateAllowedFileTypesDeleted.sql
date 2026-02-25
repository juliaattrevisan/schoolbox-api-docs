ALTER TABLE allowedFileTypes CHANGE deleted _deleted TINYINT(1) DEFAULT 0 NOT NULL;
DROP INDEX deleted ON allowedFileTypes;
