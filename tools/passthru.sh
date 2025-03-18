#!/bin/bash
# Pass any shell command from vagrant host to vagrant guest command line.
# Command runs in /usr/share/schoolbox/ directory.
#
# Usage:
#       tools/passthru.sh [command]
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"

ssh_config=`vagrant ssh-config 2>/dev/null`
if [ -n "$ssh_config" ]
then
	echo "Passing command \"$*\" through to Vagrant box."
	vagrant ssh-config | ssh default -q -F /dev/stdin "cd /usr/share/schoolbox/; $*"
else
	echo "This is either inside a Vagrant box, or no Vagrant box is available in this directory." >&2
	exit 1
fi

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"