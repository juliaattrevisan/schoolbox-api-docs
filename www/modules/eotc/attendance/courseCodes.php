<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$externalHandler = new \External_Synergetic_Class();
$allCodes = $externalHandler->findSubjectsWithFiletype($_POST['id']);

echo json_encode($allCodes);

exit();