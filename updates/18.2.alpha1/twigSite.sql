UPDATE config
SET value = '/feedback'
WHERE value = '/feedback.php'
  AND name = 'feedback_url';

INSERT config (name, value, title, description, field_id, options, type_id, editable, hidden, required, module)
VALUES
('site_feedback_types', 'Technical Issue,Support,Enquiry', 'Feedback Types',
 'Allow users to classify feedback when submitting feedback form. Each option should be comma separated', 1, null, 1, 'true', 'false', 'true', 'settings');

