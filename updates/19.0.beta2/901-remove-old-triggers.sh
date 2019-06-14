#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
php $fullpath/../../tools/mq/remove-old-triggers.php

