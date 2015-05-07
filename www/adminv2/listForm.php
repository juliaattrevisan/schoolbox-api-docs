<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isSuperuser()) {

    /* Set up generic table handler thing. Exclude certain columns from being
	 * interpreted as data columns.
	 */
    $exclusion = array('tableName', 'tableIndex');

    $tableIndex = Scrub::text($_POST['tableIndex']);
    $tableName = Scrub::text($_POST['tableName']);

    $genericHandler = new GenericTable($tableName, $tableIndex);
    $table = $genericHandler->getAll();

    $allColumns = array_diff(array_keys($_POST), $exclusion);

    /* Update each row
	 */
    foreach (array_keys($_POST[$tableIndex]) as $i) {
        $data = array();

        /* Accumulate a set of data from the relevant data columns
		 */
        foreach ($allColumns as $currentColumn) {
            $data[$currentColumn] = $_POST[$currentColumn][$i];
        }

        /* Determine whether this is a new record, or whether to update existing
		 */
        if (is_numeric($data[$tableIndex])) {
            $genericHandler->update($data);
        } else {
            $genericHandler->create($data);
        }
    }

    $page->backToTheFuture(1);
} else {
    error(19);
}
