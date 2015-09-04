<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

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

    // Messaging behavior may be affected by changing configuration.
    $rerouterSettings = ['messaging_enabled'];
    $reloadRerouter   = false;

    /* Accumulate a set of data from the relevant data columns
	 */
    foreach ($allRows as $currentRow) {
        /* Get the relevant record and put the new data in
		 */
        $record   = $genericHandler->getByField('name', $currentRow);
        $oldValue = $record['value'];

        /* handle conversion to bytes */
        if ($record['field_id'] == Config::FIELD_BYTES) {
            $newValue = Alaress_Byte_Format::stringToBytes($_POST[$currentRow]);
        }
        else if ($record['field_id'] == Config::FIELD_FILE) {
            $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();
            $result = $factory->build(array($_POST[$currentRow]))->getFirst();
            $newValue = $result->getHash();
        }
        else {
            $newValue = $_POST[$currentRow];
        }
        $record['value'] = $newValue;

        if (in_array($currentRow, $rerouterSettings) && $newValue != $oldValue) {
            $reloadRerouter = true;
        }

        /* Update each row
		 */
        $genericHandler->update($record);
    }

    // Reload message router
    if ($reloadRerouter) {
        $commander = new \Schoolbox\Realtime\Rerouter\RerouterCommander(new \Schoolbox\UserMapper());
        $commander->adviseReload();
    }

    $page->backToTheFuture(1);
} else {
    error(19);
}
