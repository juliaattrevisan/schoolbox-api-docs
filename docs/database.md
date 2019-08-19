#Database Standards

Tables: InnoDB
Charset: utf8mb4
Collation: utf8mb4_unicode_ci


###TABLES
All tables should now be created with InnoDB.  Where applicable please create **foreign keys**.  In most cases you should not use referential CASCADE actions.  Instead these should be kept in business logic unless absolutely required.  Use RESTRICT or NO ACTION on both update and delete.  

For now please avoid using stored procs or events on tables. We want to keep business logic out of the schema unless it is required.

###CHARSET (the way we store text)
We have now converted everything to the utf8mb4 charset.  Some things remain latin due to length of indexes or simplicity and other restrictions.  But moving forward there is no reason not to use utf8mb4.  utf8mb4 is an extension of utf8 and gives the complete charset of utf8. 

https://mathiasbynens.be/notes/mysql-utf8mb4

###COLLATION (the way we sort text)
We are now using tf8mb4_unicode_ci for all future tables.  It will sort correctly and fix all issues.

https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
