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

if [ -f .git/ref ];
then
    buildcommit=`cat .git/ref`;
else
    echo "Unable to determine build commit";
    ls -1 .git/;
fi

if [ ! -f ../build-artifact/"$branch"-build.tar.gz ];
then
	echo "Unable to locate build artifact: ../build-artifact/"$branch"-build.tar.gz";
	exit 1;
fi

echo "Creating artifact directory";
mkdir -vp $initialdir/package-artifacts;

for i in schoolbox{,dev};
do
	echo "[$i] Creating package directory";
	shortversion=`echo "$version" | cut -d'-' -f1`;
	majversion=`echo "$version" | cut -d'.' -f1-2`;
	mkdir -vp $initialdir/$i-$shortversion;

	echo "[$i] Unpacking build artifact";
	cd $initialdir/$i-$shortversion;
	tar xzf ../build-artifact/"$branch"-build.tar.gz;
	if [ $? -ne 0 ];
	then
		echo "Error unpacking build artifact";
		exit 1;
	fi

	ls -l;

  echo "Check we have the right commit in the artifacts for the package";

  echo "Build commit: $buildcommit";

  if [ -f version.json ];
  then
    buildcommit_in_artifacts=`cat version.json | grep -oP '(?<="build_commit":")[^"]*'`;
    echo "Artifacts commit: $buildcommit_in_artifacts"
  else
    echo "Unable to check commit in artifacts is valid, don't package";
    exit 1;
  fi

  if [ ! "$buildcommit_in_artifacts" == "$buildcommit" ]
  then
    echo "Build commit in artifacts is not the same as commit in current build. Do not package."
    exit 1;
  fi

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
	echo "  * Release of the $majversion series" >> debian/changelog;
	echo "" >> debian/changelog;
	curdate=`date -R`;
	echo " -- Alaress (Schoolbox Signing Key) <support@schoolbox.com.au>  $curdate" >> debian/changelog;

	echo "[$i] Creating source tarballs for packages";
	cd $initialdir;
	tar czf "$i"_"$shortversion".orig.tar.gz $i-$shortversion;
	if [ $? -ne 0 ];
	then
		echo "Unable to build source tarball for $i";
		exit 1;
	fi
	cd "$i-$shortversion";

	echo "[$i] Building Package";
	debuild -i -I -us -uc;
	if [ $? -ne 0 ];
	then
		echo "Build failed for $i";
		exit 1;
	fi

	echo "[$i] Copy deb files to package-artifacts";
	cp -vf ../*.deb $initialdir/package-artifacts;

	cd $instdir;
done

echo "Artifact List: `readlink -f $initialdir/package-artifacts/`";
ls -lhR $initialdir/package-artifacts/;

echo "Other build items";
ls -lh $initialdir;

echo "Creating package tarball";
cd $initialdir;
tar czf package.tar.gz package-artifacts;
if [ $? -ne 0 ];
then
	echo "Unable to tarball packages";
	exit 1;
fi

mv -v package.tar.gz package-artifacts/;
mv -v schoolbox_*.orig.tar.gz package-artifacts/schoolbox_"$version".orig.tar.gz;
mv -v schoolboxdev_*.orig.tar.gz package-artifacts/schoolboxdev_"$version".orig.tar.gz;
