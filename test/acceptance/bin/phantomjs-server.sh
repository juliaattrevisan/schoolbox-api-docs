#!/usr/bin/env bash
DIR=`dirname "$0"`

# does phantomjs exist?
if [ ! -f $DIR/phantomjs/bin/phantomjs ]
then
    # if not download and unarchive it
    wget -O $DIR/phantomjs.tar.bz2 https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-2.1.1-linux-x86_64.tar.bz2
    tar -xjf $DIR/phantomjs.tar.bz2 -C $DIR/
    mv $DIR/phantomjs-2.1.1-linux-x86_64 $DIR/phantomjs
fi

# run
$DIR/phantomjs/bin/phantomjs --webdriver=4444
