CREATE TABLE webdav
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  uri VARCHAR(1024),
  path VARCHAR(1024),
  username_prefix VARCHAR(100),
  username_suffix VARCHAR(100),
  campus_id INT,
  role_id INT,
  deleted_at DATETIME
);