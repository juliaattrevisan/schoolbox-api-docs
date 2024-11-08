<?php

use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\File\Extension;
use Schoolbox\File\ExtensionMapper;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\Permission\Access\UserAccess;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if (
    !$_SESSION['user']->isSuperuser() &&
    !$_SESSION['user']->hasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL)
) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$tableIndex = 'fileTypeID';

$mimeMapper = MimetypeMapper::getInstance();

/* Delete or create/update
  */
if (is_numeric($_GET['delete'] ?? null)) {
    $id = Scrub::text($_GET['delete']);

    try {
        $mimeMapper->delete($mimeMapper->findById($id));
    } catch (Schoolbox\Mapper\Exception $e) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_RESOURCE_DELETE);
    }

    $page->backToTheFuture(1);
}


/* Check that all the required fields were submitted
 */
foreach (['description', 'mime', 'extension', 'inline'] as $current) {
    if (!strlen(trim($_POST[$current]))) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
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
    $mime = $mimeMapper->findOneByColumn('mime', $_POST['mime']);

    /* Check if $mime->getFileTypeId is the same as that which was POSTed
     *
     * If fileTypeId was provided, then we perhaps tried to modify an existing
     * instance to inadvertently have the same mime as another instance
     */
    if (isset($_POST['fileTypeId']) && is_numeric($_POST['fileTypeId'])) {
        /* You can't update an existing record into one where the mimetype already exists
         */
        if ((int)$_POST['fileTypeId'] !== $mime->getFileTypeID()) {
            throw ErrorMapper::getInstance()->generateException(
                ErrorMapper::ERROR_FORM_SUBMIT,
                null,
                'The mime type you have chosen for this file type is already in use.'
            );
        }
    } else {
        /* You can't create a new record if the mime type is already in use
         */
        throw ErrorMapper::getInstance()->generateException(
            ErrorMapper::ERROR_FORM_SUBMIT,
            null,
            'The mime type you have chosen for this file type is already in use.'
        );
    }
} catch (\Schoolbox\Mapper\Exception\Deleted $e) {
    /** @var \Schoolbox\File\Mimetype $mime */
    $mime = $e->getDeletedObject();
} catch (\Schoolbox\Mapper\Exception\NotFound $e) {
    $mime = $mimeMapper->createModel();
}


$mime->setDescription($_POST["description"]);
$mime->setMime($_POST["mime"]);
$mime->setIconFile($_POST["iconFile"]);
$mime->setInline($_POST["inline"] === 'true');
$mime->setEnable($_POST["enable"] === 'true');
$mime->setMaxFileSize($_POST["max_file_size"]);
$mime->setDeleted(false);

if (isset($_POST['fileTypeId']) && is_numeric($_POST['fileTypeId'])) {
    $mime->setId($_POST['fileTypeId']);
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
