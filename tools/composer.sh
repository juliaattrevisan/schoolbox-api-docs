#!/bin/bash
# Composer update script
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

# Go to parent directory of this file, and run Composer from there.
cd $(dirname $(dirname $(readlink -f $0)))
export XDEBUG_MODE=off
composer install --no-progress -o $*
checkexit=`echo $?`;
if [ $checkexit -eq 0 ];
then
	sha1sum composer.lock > vendor/composer.lock.checksum;
fi
exit $checkexit;
