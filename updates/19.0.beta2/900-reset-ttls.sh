#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
php $fullpath/../../tools/mq/reset-ttls.php

