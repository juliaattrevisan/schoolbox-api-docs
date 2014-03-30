#!/bin/bash
cd ~;
mkdir build-dir-`date -I`;
cd build-dir-`date -I`;
cp -fr ~/debian .;
cp -fr ~/schoolbox-epl.conf .;
cp -fR ../schoolbox-epl.logrotate .;
wget -c https://github.com/ether/etherpad-lite/archive/1.3.0.tar.gz;
tar xzf 1.3.0.tar.gz;
cd etherpad-lite-1.3.0/src;
npm install;
npm install redis;
cd ../;
cp -fR ../schoolbox-epl.conf .;
cp -fR ../schoolbox-epl.logrotate schoolbox-epl;
cd ../;
tar czf schoolbox-epl_1.3.0.orig.tar.gz etherpad-lite-1.3.0;
cd etherpad-lite-1.3.0;
cp -fR ../debian .;
#copy in patches (logrotate, upstart)
debuild -i -I -us -uc;
