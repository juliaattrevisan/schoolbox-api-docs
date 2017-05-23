CREATE TABLE pastoral_report_dashboard
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  report_id INT,
  created_at DATETIME,
  updated_at DATETIME,
  deleted_at DATETIME
);