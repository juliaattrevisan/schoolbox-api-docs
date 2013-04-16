#!/bin/bash
# Composer update script
#
# @author      Amri Sannang <amri.sannang@alaress.com.au>
# @copyright   2013 Alaress Pty Ltd
# @link        http://www.alaress.com.au

# Go to parent directory of this file, and run Composer from there.
cd $(dirname $(dirname $(readlink -f $0)))
php composer.phar update
