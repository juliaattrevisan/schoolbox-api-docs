<?php

use Schoolbox\Collection;
use Schoolbox\File\Extension;
use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

$tableIndex = 'fileTypeID';

$mimeMapper = MimetypeMapper::getInstance();

/* Delete or create/update
  */
if (is_numeric($_GET['delete'])) {
    $id = Scrub::text($_GET['delete']);

    try {
        $mimeMapper->delete($mimeMapper->findById($id));
    } catch (Schoolbox\Mapper\Exception $e) {
        // invalid ID
        error(8);
    }

    $page->backToTheFuture(1);
}


/* Check that all the required fields were submitted
 */
foreach (['description', 'mime', 'extension', 'inline'] as $current) {
    if (!strlen(trim($_POST[$current]))) {
        error(2);
    }
}

$allExtensions = [];

// splice off extensions
if (isset($_POST['extension'])) {
    $allExtensions = explode(',', $_POST['extension']);
    unset($_POST['extension']);
}

// convert from human-readable to machine-readable
if (isset($_POST['max_file_size'])) {
    $_POST['max_file_size'] = Alaress_Byte_Format::stringToBytes($_POST['max_file_size']);
}

/* Try to load an existing Mimetype that used $_POST['mime']
 * One of these instance might already exist but was deleted
 * In that case, resurrect it and give it the new settings
 *
 * Otherwise create a new instance and continue normally
 */
try {
    $mime = $mimeMapper->findOneByColumn('mime', $_POST['mime'], [
        'deleted' => true
    ]);
} catch (\Schoolbox\Mapper\Exception\Deleted $e) {
    $mime = $e->getDeletedObject();

    /* Resurrect it
     */
    $mime->setDeleted(0);
} catch (\Schoolbox\Mapper\Exception\NotFound $e) {
    $mime = $mimeMapper->createModel();
}

$mime->setDescription($_POST["description"]);
$mime->setMime($_POST["mime"]);
$mime->setIconFile($_POST["iconFile"]);
$mime->setInline($_POST["inline"] === 'true');
$mime->setEnable($_POST["enable"] === 'true');
$mime->setMaxFileSize($_POST["max_file_size"]);

if (isset($_POST['fileTypeId']) && is_numeric($_POST['fileTypeId'])) {
    $mime->setId($_POST['fileTypeId']);
}


// verify data integritae (no duplicate mimes)
try {
    /** @var Mimetype $existing */
    $existing = $mimeMapper->findOneByColumnList([
        'mime'    => $mime->getMime(),
        'deleted' => 0
    ]);

    if ($existing->getId() != $mime->getId()) {
        error(105, 'The mime type you have chosen for this file type is already in use.');
    }
} catch (Schoolbox\Mapper\Exception $e) {
    // doesn't exist: noop
}

// save that thing, and get the ID in case it's new
$ids = $mimeMapper->save($mime);
$id  = count($ids) ? $ids[0] : 0;

if (count($allExtensions)) {
    $extMapper = ExtensionMapper::getInstance();

    // remove the existing extensions for this mime record
    $extMapper->deleteByColumn('fileTypeID', $id);

    $exts = new Collection();

    // collate the list of extensions
    foreach ($allExtensions as $extension) {
        /** @var Extension $ext */
        $ext = $extMapper->createModel();
        $ext->setId($id);
        $ext->setExtension($extension);

        $exts->add($ext);
    }

    $extMapper->save($exts);
}

$page->backToTheFuture(1);
