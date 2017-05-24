CREATE TABLE pastoral_report_dashboard
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT(4) UNSIGNED,
  report_id INT,
  created_at DATETIME,
  updated_at DATETIME,
  deleted_at DATETIME
);

ALTER TABLE pastoral_report_dashboard MODIFY user_id INT(4) unsigned NOT NULL;
ALTER TABLE pastoral_report_dashboard MODIFY report_id INT(11) NOT NULL;

ALTER TABLE pastoral_report_groups ADD CONSTRAINT pastoral_report_groups_pastoral_report_id_fk FOREIGN KEY (report_id) REFERENCES pastoral_report (id);
ALTER TABLE pastoral_report_groups ADD CONSTRAINT pastoral_report_groups_pastoral_group_id_fk FOREIGN KEY (group_id) REFERENCES pastoral_group (id);

ALTER TABLE pastoral_report_dashboard ADD CONSTRAINT pastoral_report_dashboard_pastoral_report_id_fk FOREIGN KEY (report_id) REFERENCES pastoral_report (id);
ALTER TABLE pastoral_report_dashboard ADD CONSTRAINT pastoral_report_dashboard_user_id_fk FOREIGN KEY (user_id) REFERENCES user (id);

ALTER TABLE pastoral_report MODIFY owner_id INT(4) UNSIGNED;
ALTER TABLE pastoral_report ADD CONSTRAINT pastoral_report_user_id_fk FOREIGN KEY (owner_id) REFERENCES user (id);

