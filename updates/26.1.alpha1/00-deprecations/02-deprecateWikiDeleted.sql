ALTER TABLE wiki CHANGE deleted _deleted ENUM ('true', 'false') null;
DROP INDEX deleted ON wiki;
