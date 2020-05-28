#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../`;

sass=0
if [ "$1" == "--sass" ]; then
	sass=1
fi

if [ $sass = 1 ]; then
	echo "Build SASS files";
	grunt sass;
fi
