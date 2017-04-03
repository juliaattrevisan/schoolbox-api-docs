<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';

$page = new SitePage();

if ($_SESSION['user']->isSuperuser()) {
    /* Set up generic table handler thing.
	 */
    $tableIndex = 'configID';
    $tableName = 'config';

    $genericHandler = new GenericTable($tableName, $tableIndex);
    $table = $genericHandler->getAll();

    $allRows = array_keys($_POST);

    // Messaging behavior may be affected by changing configuration.
    $rerouterSettings = [ 'messaging_enabled', 'mail_replyto_enabled' ];
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
            try {
                $result   = $factory->build(array($_POST[$currentRow]))->getFirst();
                $newValue = $result->getHash();
            } catch (\Schoolbox\Resource\Exception $e) {
                // File is empty or not found. Delete the record completely
                $newValue = '';
            } catch (\InvalidArgumentException $e) {
                $newValue = '';
            }
        }
        else if ($record['field_id'] == Config::FIELD_FILEMULTI) {
            $factory = new \Schoolbox\Resource\Attachment\Collector\CollectorFactory();
            try {
                $newValue = '';
                foreach ($_POST[$currentRow] as $file) {
                    $image = $factory->build(array($file))->getFirst();
                    if ($newValue != '') {
                        $newValue .= ',';
                    }
                    $newValue .= $image->getHash();
                }
            } catch (\Schoolbox\Resource\Exception $e) {
                // File is empty or not found. Delete the record completely
                $newValue = '';
            } catch (\InvalidArgumentException $e) {
                $newValue = '';
            }
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
        // TODO implement REDIS publish
    }

    $page->backToTheFuture(1);
} else {
    error(19);
}
