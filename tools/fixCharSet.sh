
/etc/init.d/apache2 stop
mysqldump -uroot -p schoolbox --default-character-set=latin1 --skip-set-charset > /tmp/fixcharset.sql
mysql -uroot -p --default-character-set=utf8 schoolbox < /tmp/fixcharset.sql
/etc/init.d/apache2 start