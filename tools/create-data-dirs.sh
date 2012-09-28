#!/bin/bash
# Create Data Directories Script
# Description:
#	Performs a soft creation of each data directory, and sets permissions
#	Only to be used by packaging post install
#
# @author      Sam Bailey <sam.bailey@alaress.com.au>
# @copyright   2012 Alaress Pty Ltd
# @link        http://www.alaress.com.au
#

echo "Updating data directory permissions";

# First level dirs
mkdir -p /var/lib/schoolbox/{dropbox,files,myFiles,returns,storage,submissions,thumbnails,zip}

# Cache dirs
mkdir -p /var/lib/schoolbox/cache/{portrait,purifier,stream,weather,xml}

# Fix permissions
chown -R www-data:www-data /var/lib/schoolbox
chmod -R 755 /var/lib/schoolbox

# End of script