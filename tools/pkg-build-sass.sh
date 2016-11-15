#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../`;

sass=0
if [ "$1" == "--sass" ]; then
	sass=1
fi

echo "Install SASS";
sassversion='3.4.13';
which sass >/dev/null 2>&1;
if [ $? -ne 0 ];
then
	gem install sass --no-rdoc --no-ri --version "$sassversion"
else
	if [ `sass -v 2>/dev/null | cut -d' '  -f2` != "3.4.13" ];
	then
		gem install sass --no-rdoc --no-ri --version "$sassversion"
	fi
fi

if [ $sass = 1 ]; then
	echo "Build SASS files (Vagrant provision only, not package build)";
	grunt sass;
fi
