#!/bin/bash
filepath=`readlink -f $0`;
fullpath=${filepath%/*};
php $fullpath/../../tools/notif/add-msg-to-object-queue.php

