UPDATE config SET options = 'list;List (left justified images):list-reversed;List (right justified images):card;Card' WHERE name like 'comms_news_article_list_layout';

UPDATE config SET title='Allow staff to delete feedback and grades', description='If toggled to \'ON\', staff can delete their own historically published grades and feedback from a student’s due work. If toggled to ‘OFF’, only superusers are able to delete feedback and grades.' WHERE name like 'lms_permit_staff_feedback_delete';


