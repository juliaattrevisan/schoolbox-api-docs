#!/bin/bash

filepath=`readlink -f $0`;
fullpath=${filepath%/*}
"$fullpath"/codecept-run.sh smoketests
