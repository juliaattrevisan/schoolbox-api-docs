<?php
/**
 * Get a list of students' email addresses, for a given classCode
 * and set of externalIds
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Mail\AbstractMailer;

require_once __DIR__ . '/../../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/* avviare la sessione con il solito sitepage
 */
$page = new SitePage();
$externalClass = new External_Class();

$ids = array();
$emailString = array();

// Receive the POST payload as JSON string and decode the json into an associative array
$postData = json_decode(file_get_contents('php://input'), true);

/* Find all the students whose parents we'll need to contact
 */
$allStudents = $externalClass->getStudents($postData['classCode']);

if ($allStudents && count($allStudents) && is_array($postData['allIds']) && count($postData['allIds'])) {

    $userMapper = new \Schoolbox\UserMapper();
    foreach ($allStudents as $currentStudent) {
        if (in_array($currentStudent['synergy_id'], $postData['allIds'], true)) {
            try {
                $user = $userMapper->findByExternalId($currentStudent['synergy_id']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // Probably this is an user who is only in SIS. Ignoring.
                continue;
            }
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
