#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

for vhost in `ls -1 /etc/apache2/sites-available/apache-vhost-$package{,.conf,-*} 2>/dev/null`;
do
	# Check if already updated
	if [ `grep '512M' $vhost | wc -l` -gt 0 ];
	then
		echo "Fixing upload max size: $vhost";
		sed -i 's/512M/1024M/g' $vhost;
	fi

	sed -i 's/Schoolbox\ 13.5/Schoolbox\ 14.0/g' $vhost;
done

for vhost in `ls -1 /etc/apache2/sites-available/apache-vhost-$package{,.conf,-*} 2>/dev/null | grep -v dav`;
do
	# Check if already updated
	if [ `grep 'favicon.ico.php' $vhost | wc -l` -eq 0 ];
	then
		echo "Fixing favicon: $vhost";
		sed -i 's/# Force SSL/\n        RewriteCond \%\{REQUEST_URI\} \^\/favicon.ico\$\n        RewriteRule \^(.*)\$ \/favicon.ico.php [L,R=301]\n\n        # Force SSL/g' $vhost;
	fi

	if [ `grep '/www/liss' $vhost | wc -l` -eq 0 ];
	then
		echo "Adding LISS exceptions: $vhost";
		vfile=$vhost;
		tfile="/tmp/vhostfix";
		tempfile="/tmp/apache-vhost-test";
		rm -f $tempfile;
		cp -f $vfile $tfile;

		tline=`grep -n '# MGS Sport' $tfile | cut -d':' -f1`;
		pline=`grep -n '# MGS Sport' $tfile | cut -d':' -f1 | wc -l`;
		 wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate '# MGS Sport' in: $vhost";
			exit 0;
		else
			head -n `expr $tline - 1` $tfile > $tempfile;
			echo "        # LISS Exceptions" >> $tempfile;
			echo "        <Directory /vagrant/www/liss>" >> $tempfile;
			echo "                php_value memory_limit 512M" >> $tempfile;
			echo "                php_value max_execution_time 300" >> $tempfile;
			echo "        </Directory>" >> $tempfile;
			echo "" >> $tempfile;
			tail -n `expr $wcfile - $tline + 1` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

		cp -vf $tfile $vhost;
		rm -f $tfile;
	fi

	if [ `grep '^/login' $vhost | wc -l` -eq 0 ];
	then
		echo "Fixing SSL'd areas: $vhost";
		vfile=$vhost;
		tfile="/tmp/vhostfix";
		tempfile="/tmp/apache-vhost-test";
		rm -f $tempfile;
		cp -f $vfile $tfile;

		tline=`grep -n 'RewriteCond %{REQUEST_URI} \^\/\\$' $tfile | cut -d':' -f1`;
		pline=`grep -n 'RewriteCond %{REQUEST_URI} \^\/\\$' $tfile | cut -d':' -f1 | wc -l`;
		ishash=`grep 'RewriteCond %{REQUEST_URI} \^\/\\$' $tfile | grep '#' | wc -l`;
		if [ $ishash -eq 1 ];
		then
			ishash='#';
		else
			ishash='';
		fi;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'RewriteCond %{REQUEST_URI} ^/$' in: $vhost";
			exit 0;
		else
			head -n `expr $tline - 1` $tfile > $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/login [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/login/.* [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/adminv2/.* [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/m/.* [OR]" >> $tempfile;
			tail -n `expr $wcfile - $tline` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

		tline=`grep -n 'RewriteCond %{REQUEST_URI} !\^\/\\$' $tfile | cut -d':' -f1`;
		pline=`grep -n 'RewriteCond %{REQUEST_URI} !\^\/\\$' $tfile | cut -d':' -f1 | wc -l`;
		ishash=`grep 'RewriteCond %{REQUEST_URI} !\^\/\\$' $tfile | grep '#' | wc -l`;
		if [ $ishash -eq 1 ];
		then
			ishash='#';
		else
			ishash='';
		fi;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'RewriteCond %{REQUEST_URI} !^/$' in: $vhost";
			exit 0;
		else
			head -n `expr $tline - 1` $tfile > $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/login" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/login/.*" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/adminv2/.*" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/m/.*" >> $tempfile;
			tail -n `expr $wcfile - $tline` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

		cp -vf $tfile $vhost;
		rm -f $tfile;
	fi
done
