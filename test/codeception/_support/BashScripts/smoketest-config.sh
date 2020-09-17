#!/bin/bash

initialdir=`pwd`;

mkdir -vp $initialdir/qa-container-config;
echo "operating in dir: `pwd`";

cp schoolbox-develop/test/condeception/_data/SmokeTests/configfiles.tar.gz $initialdir/qa-container-config/qa-container-config.tar.gz
ls -lhR $initialdir/qa-container-config;

