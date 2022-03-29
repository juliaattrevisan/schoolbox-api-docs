#!/bin/bash

# This script has been superseded by the generic script `vagrant-sync-repo.sh`
dir=`dirname "$(realpath $0)"`
$dir/vagrant-sync-repo.sh $1 $2 stable
