#!/bin/bash

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
"$fullpath"/codeception-run.sh Api
