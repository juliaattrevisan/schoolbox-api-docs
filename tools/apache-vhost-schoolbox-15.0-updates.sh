#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

for vhost in `ls -1 /etc/apache2/sites-*/apache-vhost-$package{,.conf,-*} 2>/dev/null`;
do
	echo "Updating apache vhost: $vhost";
	# Set newrelic reporting version
        sed -i 's/Schoolbox\ 14.5/Schoolbox\ 15.0/g' $vhost;
	# Fix file detection in reqrites for SB-7874
	sed -i 's/RewriteCond\ %{REQUEST_URI}\ !\\.\[A-Za-z\]{2,4}\$/RewriteCond\ %{DOCUMENT_ROOT}%{REQUEST_URI}\ !-f/g' $vhost;
done
