#!/bin/bash
backupfolder="/etc/apache2/sites-available/pre-"`date -I`;
mkdir -p $backupfolder;

for i in `ls -1 /etc/apache2/sites-enabled/ | grep schoolboxdev`;
do
	# Backup the current active files
	cp -vf /etc/apache2/sites-enabled/$i $backupfolder/$i;
	
	# Run fixes
	if [ `echo $i | grep -i webdav | wc -l` -eq 0 ];
	then
		# Regular vhost fixes
		vfile="/etc/apache2/sites-enabled/$i";
		tfile="/tmp/vhostfix-$i";
		tempfile="/tmp/apache-vhost-test";
		rm -f $tempfile;
		cp -f $vfile $tfile;

		sed -i 's/\t/        /g' $tfile;

		tline=`grep -n 'Satisfy all' $tfile | cut -d':' -f1`;
		pline=`grep -n 'Satisfy all' $tfile | cut -d':' -f1 | wc -l`;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'Satisfy all'";
			exit 1;
		else
			head -n $tline $tfile > $tempfile;
			echo "                <LimitExcept GET POST HEAD>" >> $tempfile;
			echo "                        Order Allow,Deny" >> $tempfile;
			echo "                        Deny from all" >> $tempfile;
			echo "                        Satisfy all" >> $tempfile;
			echo "                </LimitExcept>" >> $tempfile;
			tail -n `expr $wcfile - $tline` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

		tline=`grep -n 'Minify Exceptions' $tfile | cut -d':' -f1`;
		pline=`grep -n 'Minify Exceptions' $tfile | cut -d':' -f1 | wc -l`;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'Minify Exceptions'";
			exit 1;
		else
			head -n `expr $tline - 1` $tfile > $tempfile;
			echo "" >> $tempfile;
			echo "        #Setup xhprof for debugging, only works if module is installed on server" >> $tempfile;
			echo "        Alias /xhprof /usr/share/php5-xhprof/xhprof_html" >> $tempfile
			echo "        Alias /debugbar /usr/share/schoolboxdev/vendor/maximebf/debugbar/src/DebugBar/Resources/" >> $tempfile;
			echo "        #Alias /xhprof /usr/share/schoolboxdev/vendor/facebook/xhprof/xhprof_html/" >> $tempfile;
			echo "" >> $tempfile;
			tail -n `expr $wcfile - $tline + 1` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

		sed -i '/newrelic.appname/d' $tfile;
		tline=`grep -n 'Assess uses JSON' $tfile | cut -d':' -f1`;
		pline=`grep -n 'Assess uses JSON' $tfile | cut -d':' -f1 | wc -l`;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'Assess uses JSON'";
			exit 1;
		else
			head -n `expr $tline - 1` $tfile > $tempfile;
			echo "        php_value session.save_handler redis" >> $tempfile;
			echo "        php_value session.save_path \"tcp://127.0.0.1:6379\"" >> $tempfile;
			echo "        php_value newrelic.appname \"Schoolbox Dev\"" >> $tempfile;
			echo "" >> $tempfile;
			tail -n `expr $wcfile - $tline + 1` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;

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
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/$ [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/saml [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/saml/.* [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/account/modify [OR]" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} ^/account/modify/.*" >> $tempfile;
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
			head -n `expr $tline` $tfile > $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/saml" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/saml/.*" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/account/modify" >> $tempfile;
			echo "        $ishash""RewriteCond %{REQUEST_URI} !^/account/modify/.*" >> $tempfile;
			tail -n `expr $wcfile - $tline` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;
		
		cp -vf $tfile $vfile;
		rm -f $tfile;
	else
		# Webdav vhost fixes
		vfile="/etc/apache2/sites-enabled/$i";
		tfile="/tmp/vhostfix-$i";
		tempfile="/tmp/apache-vhost-test";
		rm -f $tempfile;
		cp -f $vfile $tfile;

		sed -i 's/\t/        /g' $tfile;
		sed -i '/newrelic.appname/d' $tfile;
		tline=`grep -n 'post_max_size' $tfile | cut -d':' -f1`;
		pline=`grep -n 'post_max_size' $tfile | cut -d':' -f1 | wc -l`;
		wcfile=`wc -l $tfile| awk {'print $1'}`;
		if [ $pline -eq 0 ];
		then
			echo "Unable to locate 'post_max_size'";
			exit 1;
		else
			head -n `expr $tline - 1` $tfile > $tempfile;
			echo "        php_value session.gc_maxlifetime 360" >> $tempfile;
			echo "        php_value session.save_handler redis" >> $tempfile;
			echo "        php_value session.save_path \"tcp://127.0.0.1:6379\"" >> $tempfile;
			echo "        php_value newrelic.appname \"Schoolbox Dev\"" >> $tempfile;
			echo "" >> $tempfile;
			tail -n `expr $wcfile - $tline + 1` $tfile >> $tempfile;
			cp -f $tempfile $tfile;
			rm -f $tempfile;
		fi;
		
		cp -vf $tfile $vfile;
		rm -f $tfile;
	fi;
done;

