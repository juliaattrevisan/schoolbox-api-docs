#!/bin/bash

# SCHOOLBOX UPDATE COMPILER v1
# This script grabs a tag, and compiles it into something the updater will read from the client side, 
# before sending it to schoolbox.com.au and making it available.

# Depends on the TAG existing, /sql/update_TAG.sql existing and there being an entry in /sql/versions.txt for this tag.
# The three must be identical for everything to work OK! (Not anymore!)

if [[ $# -lt 1 ]]; then
echo "Exiting due to no Version supplied (SVN Tag)";
exit 1;
fi;
errorCheck() {
if [ $? -eq 0 ]; then
echo -e "... OK";
else
echo -e "... FAILED (Err:$?)"
if [ $@ == 1 ]; then
	echo -e "Terminating due to broken/incomplete update";
	exit;
fi;
fi;
}
clear;
echo -e "This Script is now performing the required functions to Export a new version of Schoolbox from the SVN and compile a release package.";
echo -e "\nThis involves extracting the package, ensuring the components exist that are required for an update, and updating the versions index.";
if [ -d /tmp/exported ]; then
echo -e "\n\n.... Removing old Working Directory...";
rm -Rf /tmp/exported;
errorCheck 1;
echo -e "\n... Creating Working Directory...";
mkdir /tmp/exported;
errorCheck 1;
else
echo -e "\n... Creating Working Directory...";
mkdir /tmp/exported;
errorCheck 1;
fi;

echo -e "... Entering Working Directory...";
cd /tmp/exported;
errorCheck 1; 
echo -e "... Now Performing a Subversion Export of the tag $@";
svn export --username=xxxx --password=xxxx http://192.168.1.50/svn/portal/tags/$@/ &> /dev/null
errorCheck 1;

# Remove certain files and directories that are not to be distributed
rm -rf debian/
rm -rf test/
rm -rf phpunit.xml
rm -rf .svn*
rm -rf .git*

#echo -e "Attempting to process the v$@ SQL Update file (File:/sql/update_$@.sql)";
#cp /tmp/exported/$@/sql/update_$@.sql /tmp/ &>> /dev/null;
#errorCheck 1;
echo -e "Proceeding to Process new versions index...";
if [[ `cat /tmp/exported/$@/updates/version_index|grep $@` ]]; then
echo -e "... Passed Processing found $@ in the Version Index";
else
echo -e "... Failed... new Versions index does not contain new version ($@)\n";
exit;
fi;
echo -e "Finalising Processing on new Index...";
cp /tmp/exported/$@/updates/version_index /tmp/version_index
errorCheck 1;
if [ -d /tmp/exported/$@/updates/$@ ]; then
echo -e "Found Directory for Custom Scripts...\n";
else
echo -e "Warning: Did not find custom scripts directory /updates/$@\n";
fi;
if [ -s /tmp/exported/$@/updates/$@/scripts_list ]; then
echo -e "Found Script Processing List...\n";
else
echo -e "Warning: Did not find custom scripts processing list...";
fi;
echo -e "Entering Temporary Directory...";
cd /tmp/exported/$@;
errorCheck 1;
echo -e "Creating Update package...";
tar -cvzf release_$@.tgz * &> /dev/null
errorCheck 1;
echo -e "Transferring Update package to Upload Directory..";
cp release_$@.tgz /tmp/exported/release_$@.tgz;
errorCheck 1;
echo -e "Removing Temporary Files...";
rm -Rf /tmp/exported/$@
errorCheck 1;
#echo -e "Transferring Update SQL File to Upload Directory..";
#cp /tmp/update_$@.sql /tmp/exported/update_$@.sql;
#errorCheck 1;
echo -e "Transferring Versions index to Upload Directory..";
cp /tmp/version_index /tmp/exported;
errorCheck 1;
echo -e "Succesfully Compiled... Sending to update server (schoolbox.com.au)";
scp /tmp/exported/* schoolbo@schoolbox.com.au:/home/schoolbo/www/schoolboxinstaller/
errorCheck 1;
echo -e "Cleaning up Temporary Files";
rm -Rf /tmp/exported
errorCheck 1;
