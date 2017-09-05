#!/usr/bin/env bash
DIR=`dirname "$0"`

# does chromedriver exist?
if [ ! -f $DIR/chromedriver ]
then
    # if not download and unarchive it
    wget -O $DIR/chromedriver_linux64.zip https://chromedriver.storage.googleapis.com/2.31/chromedriver_linux64.zip
    unzip $DIR/chromedriver_linux64.zip -d $DIR/
fi

# run
$DIR/chromedriver --url-base=/wd/hub
