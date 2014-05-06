#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

for vhost in `ls -1 /etc/apache2/sites-available/apache-vhost-$package{,-*} | grep -v dav`;
do
	# Check if already updated
	if [ `grep 'favicon.ico.php' $vhost | wc -l` -eq 0 ];
	then
		sed -i 's/# Force SSL/\n        RewriteCond \%\{REQUEST_URI\} \^\/favicon.ico\$\n        RewriteRule \^(.*)\$ \/favicon.ico.php [L,R=301]\n\n        # Force SSL/g' $vhost;
	fi
	if [ `grep '^/login' $vhost | wc -l` -eq 0 ];
	then
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
			echo "Unable to locate 'RewriteCond %{REQUEST_URI} ^/$'";
			exit 1;
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
			echo "Unable to locate 'RewriteCond %{REQUEST_URI} !^/$'";
			exit 1;
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
