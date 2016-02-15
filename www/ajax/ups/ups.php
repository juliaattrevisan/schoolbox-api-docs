<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$allPairs = [];
$allResults = new \Schoolbox\Collection();

$query = $_GET['query'];

if ($_GET['type'] == 'groups') {
    $mapper = new \Schoolbox\Resource\GroupMapper();
    $allResults = $mapper->findFolders($query);
}
else if ($_GET['type'] == 'campus') {
    $mapper = new \Schoolbox\CampusMapper();
    $allResults = $mapper->findCampuses($query);
}

if ($allResults->count()) {
    foreach ($allResults as $current) {
        if ($current instanceof \Schoolbox\ValueLabelInterface) {
            $pair = $current->findValueLabelPair();

            /* Translate for selectize
             */
            $allPairs[] = [
                'id'   => $pair['value'],
                'name' => $pair['label']
            ];
        }
    }
}

header("Content-type: application/json");
echo json_encode($allPairs);