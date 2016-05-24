UPDATE msg_source SET description = 'you have pending work that is due soon' WHERE description = 'you have outstanding work that is due soon';
UPDATE msg_source SET description = 'you have pending work that is overdue' WHERE description = 'you have outstanding work that is overdue';
UPDATE msg_source SET description = 'a submission box has closed with submissions pending' WHERE description = 'a submission box has closed with submissions outstanding';
UPDATE msg_source SET description = 'your child has pending work that is overdue' WHERE description = 'your child has outstanding work that is overdue';
UPDATE msg_source SET description = 'your child has pending work that is due soon' WHERE description = 'your child has outstanding work that is due soon';
