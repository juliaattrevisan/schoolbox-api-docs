#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../`;
PATH="$PATH:$instdir/node_modules/.bin";

echo "Installing NPM dependencies";
npm ci;
check=$?;
if [ $? -ne 0 ];
then
	echo "An error occurred";
fi

sha1sum npm-shrinkwrap.json > node_modules/npm.shrinkwrap.checksum;
exit $check;
