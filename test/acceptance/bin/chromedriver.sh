#!/usr/bin/env bash
DIR=`dirname "$0"`

# does chromedriver exist?
if [ ! -f $DIR/chromedriver ]
then
    # if not download and unarchive it
    wget -O $DIR/chromedriver_linux64.zip https://chromedriver.storage.googleapis.com/2.31/chromedriver_linux64.zip
    unzip $DIR/chromedriver_linux64.zip -d $DIR/
fi

# is it not in our PATH?
if hash chromedriver 2>/dev/null; then
    echo "chromedriver already exists in PATH"
else
    sudo ln -v --symbolic "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/chromedriver" /usr/bin/chromedriver
fi
