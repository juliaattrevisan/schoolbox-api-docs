#!/bin/bash

helpmsg() {
	echo "Usage: pkg-build.sh [commithash/committag] [main/dev] [release/snapshot]";
	exit 1;
}

if [ ! -z $1 ];
then
	if [ $1 == "-h" -o $1 == "--help" -o $1 == "-help" -o $1 == "help" ];
	then
		helpmsg;
	fi
	if [ -z $2 ];
	then
		echo "Please provide a pkg type (main/dev)";
		helpmsg;
	fi
	if [ -z $3 ];
	then
		# Assume if not specified, building a snapshot
		reltype="snapshot";
	else
		if [ $3 == "release" ];
		then
			reltype="release";
		else
			reltype="snapshot";
		fi
	fi
	if [ ! -d /home/vagrant/schoolbox/.git ];
	then
		echo "Creating Initial Git Directory";
		rm -rf /home/vagrant/schoolbox;
		mkdir -p /home/vagrant/schoolbox;
		cd ~/schoolbox;
		git init;
		git remote add origin git@github.com:alaress/schoolbox.git;
	fi
	if [ -d "/home/vagrant/schoolbox/.git" ];
	then
		echo "Building";
		rm ~/{tarballs,build-area}/* -fR;
		cd ~/schoolbox;
		git checkout master;
		git config --add remote.origin.fetch "+refs/pull/*/head:refs/remotes/origin/pr/*"
		git fetch origin;
		git checkout $1;
		git branch -D master;
		git checkout -b master;
		git branch -D upstream;
		git branch upstream;
		commitdate=`git log -n 1 | grep "Date:" | cut -c9-32`
		# Build changelog (and release version) based on type of release
		if [ $reltype == "release" ];
		then
			git-dch -R -s HEAD --id-length=5 --no-full
		else
			git-dch -S -s HEAD --id-length=5 --no-full --snapshot-number=`date --date="$commitdate" +%Y%m%d%H%M%S`
			sed -i "s/ubuntu1//g" debian/changelog;
		fi
		# change names to match dev packaging
		if [ $2 == "dev" ];
		then
			sed -i "s/schoolbox/schoolboxdev/g" debian/*
			sed -i "s/schoolboxdev.com.au/schoolbox.com.au/g" debian/*
			for i in `ls -1 debian/schoolbox*`;
			do
				mv $i `echo $i | sed "s/schoolbox/schoolboxdev/g"`;
			done
		fi
		sed -i "s/`grep '\-\- vagrant' debian/changelog | cut -d' ' -f3`\ `grep '\-\- vagrant' debian/changelog | cut -d' ' -f4`/Alaress\ \(Schoolbox\ Signing\ Key\)\ <support@schoolbox.com.au>/g" debian/changelog;\
		git add debian;
		git commit -a -m "update changelog for building";
		debver=`dpkg-parsechangelog | sed -n -e 's/^Version: //p'`;
		version=`echo $debver | sed -e 's/^[[:digit:]]*://' -e 's/[~-].*//'`;
		git checkout upstream;
		echo "Deleting old upstream tag: $version";
		git tag -d upstream/$version;
		echo "Deleting old upstream tag: $version";
		git tag upstream/$version;
		git checkout master;
		echo "Begin package build";
		git-buildpackage -us -uc
	else
		echo "Unable to create git repository for cloning.";
		exit 1;
	fi
else
	echo "Please provide a git commit to build";
	helpmsg;
fi
