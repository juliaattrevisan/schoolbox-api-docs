<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// no sitepage allowed
if(isset($this)) {
    $this->localRedir('/adminv2/rubricresults');
}

require_once(dirname(__FILE__).'/../../library/Bootstrap.php');

if(!isset($_POST['action'])) {
    httpError(400, 2);
}

if($_POST['action'] == 'set') {
    setRubricResultSets($_POST['items']);
}
else {
    httpError(400, 2);
}



function setRubricResultSets($items) {
    if(!(is_array($items) && count($items))) {
        httpError(400, 2);
    }

    $stmt = Database::getInstance()->prepare("
        INSERT INTO rubric_result
        VALUES (:setid, :value, :label)
        ON DUPLICATE KEY UPDATE label = :label
    ");

    foreach($items as $setID => $results) {
        if(!(is_array($results) && count($results) && is_int($setID))) {
            continue;
        }
        // strip any values that might have been inserted as keys
        $results = array_values($results);

        // do a cursory check that none of the labels are blank
        foreach($results as $lbl) {
            if(strlen($lbl) == 0) {
                httpError(400, 2);
            }
        }

        foreach($results as $value => $label) {
            printf("[%s][%s] = %s\n", $setID, $value, $label);
            $stmt->execute(array(
                'setid' => $setID + 1, // because SQL begins at 1...
                'value' => $value,
                'label' => $label
            ));
        }
    }
}


