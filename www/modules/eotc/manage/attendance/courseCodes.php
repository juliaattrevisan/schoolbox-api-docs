<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$authenticator = new \EOTC\Authenticator($this);

if ($authenticator->isEOTCCreator($_SESSION['user'])) {
    $externalHandler = new \External_Class();
    $allCodes        = $externalHandler->findClassesByType($_POST['id'], $_POST['campus']);

    echo json_encode($allCodes);
}

exit();
