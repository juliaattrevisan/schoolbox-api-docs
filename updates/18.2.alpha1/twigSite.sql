UPDATE config
SET value = '/feedback'
WHERE value = '/feedback.php'
  AND name = 'feedback_url';

INSERT config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES
('site_feedback_types', 'Technical Issue,Support,Enquiry', 'Feedback Types',
 'Allow users to classify feedback when submitting feedback form. Each option should be comma separated', 1, null, 1, 'true', 'false', 'true', 'settings');


-- Update feedback types for CIS
UPDATE    		`config` AS config1
 INNER JOIN		`config` AS config2
 ON config2.`value` = 'cis' AND config2.`name` = 'site_location'
SET       		config1.`value` = 'Website (cis.edu.sg),My.CIS (my.cis.edu.sg),Mobile App,Campus Online or Sodexo,Student Records Management,Conference registration,Activities and sports registration,Other'
WHERE     		config1.`name` = 'site_feedback_types';

-- Update feedback types for Dio
UPDATE    		`config` AS config1
 INNER JOIN		`config` AS config2
 ON config2.`value` = 'diocesan' AND config2.`name` = 'site_location'
SET       		config1.`value` = 'Technical Issue,Support,Enquiry,Complaint'
WHERE     		config1.`name` = 'site_feedback_types';

-- Update feedback types for TCGI
UPDATE    		`config` AS config1
 INNER JOIN		`config` AS config2
 ON config2.`value` = 'tcgi' AND config2.`name` = 'site_location'
SET       		config1.`value` = 'Academic Query,Administrative Enquiry,Schoolbox Help'
WHERE     		config1.`name` = 'site_feedback_types';
