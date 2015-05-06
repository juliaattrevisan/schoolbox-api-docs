<?php
use Schoolbox\Collection;
use Schoolbox\File\Extension;
use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$page = new SitePage();
$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

/* Check that all the required fields were submitted
 */
foreach (['description', 'mime', 'extension', 'inline'] as $current) {
    if (!strlen(trim($_POST[$current]))) {
        error(2);
    }
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

// sure... because this can't go badly.....
/** @var Mimetype $mime */
$mime = $mimeMapper->hydrate($_POST, '', function ($self, $model, $row) { return $model; });

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
