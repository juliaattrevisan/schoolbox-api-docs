#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../`;
PATH="$PATH:$instdir/node_modules/.bin";

which grunt >/dev/null 2>&1;
if [ $? -ne 0 ];
then
	echo "Unable to locate 'grunt'";
	exit 1;
fi

echo "Installing Bower submodules";
grunt bower;
check=$?;
if [ $check -ne 0 ];
then
	echo "An error occurred";
	exit $check;
fi

echo "Building JS files (Webpack)";
npm run publish;
check=$?;
if [ $check -ne 0 ];
then
	echo "An error occurred";
	exit $check;
fi

echo "Building JS files (Grunt)";
grunt build.js;
check=$?;
if [ $check -ne 0 ];
then
	echo "An error occurred";
	exit $check;
fi

sha1sum bower.json > www/javascript/components/bower.checksum;
