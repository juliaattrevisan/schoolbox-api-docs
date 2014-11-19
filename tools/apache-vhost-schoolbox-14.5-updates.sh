#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
instdir=`readlink -f $fullpath/../`;
package=${instdir##*/};

for vhost in `ls -1 /etc/apache2/sites-available/apache-vhost-$package{,.conf,-*} 2>/dev/null`;
do
	sed -i 's/Schoolbox\ 14.0/Schoolbox\ 14.5/g' $vhost;
done

