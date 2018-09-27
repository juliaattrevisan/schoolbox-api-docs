INSERT INTO schoolbox.config
(
name,
value,
title,
description,
field_id,
options,
type_id,
editable,
hidden,
required
)
VALUES
(
'show_court_order_student_profile',
'0',
'Court Order Visible On Student Profile',
'If selected, a "Court Order" alert will display under the "Contact Information" section student profiles where applicable (Displayed to users in Staff role only).',
5,
null,
15,
'true',
'false',
'true'
);