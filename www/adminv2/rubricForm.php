<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// no sitepage allowed
if (isset($this)) {
    $this->localRedir('/adminv2/rubricresults');
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

if (!$kernel->getDependencyInjector()->get('session')->get('user')->isSuperuser()) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW, '', 403);
}

if (!isset($_POST['action'])) {
    httpError(400, 2);
}

if ($_POST['action'] == 'set') {
    setRubricResultSets($_POST['items']);
} else {
    httpError(400, 2);
}



function setRubricResultSets($items)
{
    if (!(is_array($items) && count($items))) {
        httpError(400, 2);
    }

    $stmt = Database::getInstance()->prepare("
        INSERT INTO rubric_result
        VALUES (:setid, :value, :label)
        ON DUPLICATE KEY UPDATE label = :label
    ");

    foreach ($items as $setID => $results) {
        if (!(is_array($results) && count($results) && is_int($setID))) {
            continue;
        }
        // strip any values that might have been inserted as keys
        $results = array_values($results);

        // do a cursory check that none of the labels are blank
        foreach ($results as $lbl) {
            if (strlen($lbl) == 0) {
                httpError(400, 2);
            }
        }

        foreach ($results as $value => $label) {
            /* Forcibly truncate $label values so they don't exceed Model/LABEL_MAXLENGTH chars
             * This is the maximum width allowed in our db schema and will cause sentry errors
             * unless we forcibly ensure that it doesn't exceed the space available
             */
            $truncatedLabel = substr($label, 0, \Schoolbox\Assessment\Rubric\DataModel\Result\Model::LABEL_MAXLENGTH);

            printf("[%s][%s] = %s\n", $setID, $value, $truncatedLabel);

            $stmt->execute(array(
                'setid' => $setID + 1, // because SQL begins at 1...
                'value' => $value,
                'label' => $truncatedLabel
            ));
        }
    }
}
