#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

for vhost in `ls -1 /etc/apache2/sites-available/apache-vhost-$package{,.conf,-*} 2>/dev/null | grep -v dav`;
do
	if [ `grep 'ajax' $vhost | wc -l` -eq 0 ];
	then
		echo "Fixing SSL'd areas: $vhost";
		vfile=$vhost;
		tfile="/tmp/vhostfix";
		tempfile="/tmp/apache-vhost-test";
		rm -f $tempfile;
		cp -f $vfile $tfile;

		tline=`grep -n 'RewriteCond %{REQUEST_URI} !\^\/account\/modify\/.*' $tfile | cut -d':' -f1`;
		pline=`grep -n 'RewriteCond %{REQUEST_URI} !\^\/account\/modify\/.*' $tfile | cut -d':' -f1 | wc -l`;
		ishash=`grep 'RewriteCond %{REQUEST_URI} !\^\/account\/modify\/.*' $tfile | grep '#' | wc -l`;
		if [ $ishash -eq 1 ];
		then
			ishash='#';
		else
			ishash='';
		fi;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'RewriteCond %{REQUEST_URI} !^/account/modify/.*' in: $vhost";
			exit 0;
		else
			head -n `expr $tline` $tfile > $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/ajax/.*" >> $tempfile;
			tail -n `expr $wcfile - $tline` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

		cp -vf $tfile $vhost;
		rm -f $tfile;
	fi
done
