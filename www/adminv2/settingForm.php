<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isSuperuser()) {

	/* Set up generic table handler thing.
	 */
	$tableIndex = 'configID';
	$tableName = 'config';

	$genericHandler = new GenericTable($tableName, $tableIndex);
	$table = $genericHandler->getAll();

	$allRows = array_keys($_POST);

	/* Accumulate a set of data from the relevant data columns
	 */
	foreach($allRows as $currentRow) {

		/* Get the relevant record and put the new data in
		 */
		$record = $genericHandler->getByField('name', $currentRow);

        /* handle conversion to bytes */
        if($record['field']=='bytes')
            $record['value'] = Alaress_Byte_Format::stringToBytes($_POST[$currentRow]);
        else    
		    $record['value'] = $_POST[$currentRow];

		/* Update each row
		 */
		$genericHandler->update($record);
	}

	$page->backToTheFuture(1);
}
else {
	error(19);
}
?>