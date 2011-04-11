<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/sitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/generic.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isSuperuser()) {

	/* Set up generic table handler thing. Exclude certain columns from being
	 * interpreted as data columns.
	 */
	$exclusion = array('tableName', 'tableIndex');

	$tableIndex = scrubTEXT($_POST['tableIndex']);
	$tableName = scrubTEXT($_POST['tableName']);

	$genericHandler = new GenericTable($tableName, $tableIndex);
	$table = $genericHandler->getAll();

	$allColumns = array_diff(array_keys($_POST), $exclusion);

	/* Update each row
	 */
	for ($i=0; $i<count($_POST[$tableIndex]); $i++) {
		$data = array();

		/* Accumulate a set of data from the relevant data columns
		 */
		foreach ($allColumns as $currentColumn) {
			$data[$currentColumn] = scrubTEXT($_POST[$currentColumn][$i]);
		}

		/* Determine whether this is a new record, or whether to update existing
		 */
		if (is_numeric($data[$tableIndex])) {
			$genericHandler->update($data);
		}
		else {
			$genericHandler->create($data);
		}
	}
	
	$page->backToTheFuture(1);
}
else {
	error(19);
}
?>