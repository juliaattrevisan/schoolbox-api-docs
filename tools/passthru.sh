#!/bin/bash
# Pass any shell command from vagrant host to vagrant guest command line.
# Command runs in /vagrant/ directory.
#
# Usage:
#       tools/passthru.sh [command]
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au
ssh_config=`vagrant ssh-config 2>/dev/null`
if [ -n "$ssh_config" ]
then
	echo "Passing command \"$*\" through to Vagrant box."
	vagrant ssh-config | ssh default -q -F /dev/stdin "cd /vagrant/; $*"
else
	echo "This is either inside a Vagrant box, or no Vagrant box is available in this directory."
	exit 1
fi
