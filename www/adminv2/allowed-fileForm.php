<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

$page = new SitePage();
$groupHandler = new Group();

if ($groupHandler->isSuperuser()) {
    $tableIndex = 'fileTypeID';

    /* Set up generic table handler thing.
      */
    $allowedFileTypesHandler = new GenericTable('allowedFileTypes', $tableIndex);
    $allowedFileExtensionHandler = new GenericTable('allowedFileExtension', $tableIndex);

    /* Delete or create/update
      */
    if (is_numeric($_GET['delete'])) {
        $allowedFileTypesHandler->delete(Scrub::text($_GET['delete']));
        $allowedFileExtensionHandler->delete(Scrub::text($_GET['delete']));
    } else {
        $data = array();
        $allExtensions = array();

        $allColumns = array_keys($_POST);

        foreach ($allColumns as $currentColumn) {

            /* Don't add extension column to allowedFileTypes, but remember it for
                * addition into allowedFileExtension
                */
            if ($currentColumn == 'extension') {
                $allExtensions = explode(',', $_POST[$currentColumn]);
            }
            else {
                $data[$currentColumn] = Scrub::text($_POST[$currentColumn]);
            }
            if ($currentColumn === 'max_file_size') {
                $data[$currentColumn] =  Alaress_Byte_Format::stringToBytes($data[$currentColumn]);
            }
        }

        /* Create New or Update existing
           */
        if ($data['enable'] != 'true')
            $data['enable'] = 'false';

        if (is_numeric($_POST[$tableIndex])) {


            $allowedFileTypesHandler->update($data);

            $allowedFileExtensionHandler->delete($data['fileTypeID']);

            $fileTypeID = $data['fileTypeID'];
        }
        else {
            //
            // Check if mimetype is being used. If it is, don't save anything
            if (!$allowedFileTypesHandler->getByField('mime', $data['mime'])) {
                $fileTypeID = $allowedFileTypesHandler->create($data);
            }
            else {
                error(105, 'The mime type you have chosen for this file type is already in use.');
            }
        }

        /* Create/update valid file extensions table too
           */
        if ($fileTypeID) {
            foreach ($allExtensions as $currentExtension) {
                $data = array(
                    'fileTypeID' => $fileTypeID,
                    'extension' => Scrub::text(trim($currentExtension))
                );
                $allowedFileExtensionHandler->create($data);
            }
        }
    }

    $page->backToTheFuture(1);
}
else {
    error(19);
}
