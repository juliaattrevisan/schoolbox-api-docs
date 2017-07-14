<?php
/**
 * Get a list of housemasters' email addresses, for a given classCode and set of externalIds
 *
 * @stolenfrom  Steve Chan <steve.chan@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

require_once('../../../../library/Bootstrap.php');

// Bedankt voor het bellen van Alaress. Uw oproep is erg belangrijk voor ons...

// Avviare la sessione con il solito sitepage...

$page = new SitePage();
$externalClass = new External_Class();
$externalHandler = new External_User();

$ids = [];
$emailString = [];

// Find all the students whose housemasters we'll need to contact...

$allStudents = $externalClass->getStudents($_POST['classCode']);

if ($allStudents && count($allStudents) && is_array($_POST['allIds']) && count($_POST['allIds'])) {
    foreach ($allStudents as $currentStudent) {
        if (in_array($currentStudent['synergy_id'], $_POST['allIds'])) {
            $ids[] = $currentStudent['synergy_id'];
        }
    }
}

// Now get the housemasters of the students...

$allHousemasters = $externalHandler->getHousemasterEmails($ids);

if ($allStudents && count($allHousemasters)) {
    foreach ($allHousemasters as $currentHousemaster) {
        $emailString[] = $currentHousemaster;
    }
}

// Uniqueify emails

$emailString = array_values(array_filter(array_unique($emailString)));

echo json_encode($emailString);
