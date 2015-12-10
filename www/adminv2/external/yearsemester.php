<?php
/**
 * External DB (Synergetic) - Current Semester and Year check
 *
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (!$_SESSION['user']->isSuperuser()) {
	        error(19);
}

if (isset($_GET['id'])) {

    $synuser = new External_Synergetic_User();
    $fileSemester = $synuser->getCurrentSemester($_GET['id']);
    $fileYear     = $synuser->getCurrentYear($_GET['id']);
    echo "User ID: " . $_GET['id'] . "<br>\n";
    echo "FileYear: $fileYear FileSemester: $fileSemester <br>\n";

} else {
    echo "No user ID provided";
}
die();
