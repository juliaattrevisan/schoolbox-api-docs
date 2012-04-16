<?php
/**
 * Remove the synport cache, has been replaced with proper portrait cache
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

include('../../../include/Bootstrap.php');
$dir = Cfg::get('zip_dir');
$rtn = shell_exec("rm -f $dir/synport*");

// failure
if($rtn != '') {
    echo "Failed to remove all synport files from $dir\n";
}

?>