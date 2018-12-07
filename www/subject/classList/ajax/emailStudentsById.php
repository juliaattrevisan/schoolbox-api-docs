<?php
/**
 * Get a list of students' email addresses, for a given classCode
 * and set of externalIds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Mail\AbstractMailer;

require_once('../../../../library/Bootstrap.php');

/* avviare la sessione con il solito sitepage
 */
$page = new SitePage();
$externalClass = new External_Class();

$ids = array();
$emailString = array();

/* Find all the students whose parents we'll need to contact
 */
$allStudents = $externalClass->getStudents($_POST['classCode']);
if ($allStudents && count($allStudents) && is_array($_POST['allIds']) && count($_POST['allIds'])) {
    $userMapper = new \Schoolbox\UserMapper();
    foreach ($allStudents as $currentStudent) {
        if (in_array($currentStudent['synergy_id'], $_POST['allIds'])) {
            $user = $userMapper->findByExternalId($currentStudent['synergy_id']);
            if($user->getEmail()) {
                $emailString[] = $user->getFormattedEmailDisplay();
            }
        }
    }
}

// Uniqueify and cleanup empty string emails
$emailString = array_filter(array_unique($emailString));

header('Content-Type: application/json');
echo json_encode([implode(AbstractMailer::implodeGlue(), $emailString)]);
