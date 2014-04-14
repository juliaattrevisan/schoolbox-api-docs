#!/bin/bash
for vhost in `ls -1 /etc/apache2/sites-enabled/*schoolbox* | grep -v dav`;
do
	# Check if already updated
	if [ `grep 'favicon.ico.php' $vhost | wc -l` -eq 0 ];
	then
		sed -i 's/# Force SSL/\n        RewriteCond \%\{REQUEST_URI\} \^\/favicon.ico\$\n        RewriteRule \^(.*)\$ \/favicon.ico.php [L,R=301]\n\n        # Force SSL/g' $vhost;
	fi
done