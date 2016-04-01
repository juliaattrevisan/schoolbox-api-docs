#!/bin/bash

initialdir=`pwd`;

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
instdir=`readlink -f $fullpath/../../`;
cd $instdir;

echo "operating in dir: `pwd`";

branch='master';
if [ ! -z $1 ];
then
        branch="$1";
fi

echo "Branch: $branch";

echo "Checking version to build";
if [ -f ../schoolbox-release-$branch/version ];
then
	version=`cat ../schoolbox-release-$branch/version`;
	echo "Building version: $version";
else
	echo "Unable to determine version";
	exit 1;
fi

if [ ! -f ../build-artifact/"$branch"-build.tar.gz ];
then
	echo "Unable to locate build artifact: ../build-artifact/"$branch"-build.tar.gz";
	exit 1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/package-artifacts;

for i in schoolbox{,dev,cloud};
do
	echo "[$i] Creating package directory";
	mkdir -vp $initialdir/$i-$version;

	echo "[$i] Unpacking build artifact";
	cd $initialdir/$i-$version;
	tar xzf ../build-artifact/"$branch"-build.tar.gz;
	if [ $? -ne 0 ];
	then
		echo "Error unpacking build artifact";
		exit 1;
	fi

	ls -l;

	echo "[$i] Create package specfic directory sql";
	tools/generate-dir-sql.sh /var/lib/$i /usr/share/$i > sql/$i-package-config.sql;
	if [ $? -ne 0 ];
	then
		echo "Error creating package specific directory sql";
		exit 1;
	fi

	echo "[$i] Updating files with package specific names"
	tools/pkg-live-vs-dev.sh $i;
	if [ $? -ne 0 ];
	then
		echo "Error updating files with package specfic names";
		exit 1;
	fi


	if [ "$i" != "schoolbox" ];
	then
		echo "[$i] Changing package files";
		sed -i "s/schoolbox/$i/g" debian/*;
		sed -i "s/$i\.com\.au/schoolbox.com.au/g" debian/*;
		for x in `ls -1 debian/schoolbox*`;
		do
			mv -v $x `echo $x | sed "s/schoolbox/$i/g"`;
		done
	fi

	if [ "$i" == "schoolboxcloud" ];
	then
		echo "[$i] Removing config not needed for cloud";
	fi

	echo "[$i] Building changelog/version file for package";
	echo "$i ($version) stable; urgency=low" > debian/changelog;
	echo "" >> debian/changelog;
	shortversion=`echo "$version" | cut -d'.' -f1-2`;
	echo "  * Release of the $shortversion series" >> debian/changelog;
	echo "" >> debian/changelog;
	curdate=`date -R`;
	echo " -- Alaress (Schoolbox Signing Key) <support@schoolbox.com.au>  $curdate" >> debian/changelog;

	echo "[$i] Creating source tarballs for packages";
	cd $initialdir;
	tar czf "$i"_"$version".orig.tar.gz $i-$version;
	if [ $? -ne 0 ];
	then
		echo "Unable to build source tarball for $i";
		exit 1;
	fi
	cd "$i-$version";
	echo "[$i] Building Package";
	debuild -i -I -us -uc;

	echo "[$i] Find deb part 1";
	cp -vf *.deb $initialdir/package-artifacts;

	echo "[$i] Find deb part 2";
	cp -vf ../*.deb $initialdir/package-artifacts;

	cd $instdir;
done

echo "Artifact List: `readlink -f $initialdir/package-artifacts/`";
ls -lhR $initialdir/package-artifacts/;

echo "Other build items";
ls -lh $initialdir;
