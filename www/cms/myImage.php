<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\ProcessorFactory;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () {
        header('Cache-Control: no-store');
        redirectToBroken(504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

if (empty($_SESSION['id'])) {
    redirectToBroken(403);
}

try {
    $image = Schoolbox\Resource\My\File\Mapper::getInstance()->findById($_GET['id']);
} catch (Schoolbox\Mapper\Exception $e) {
    redirectToBroken(404);
    exit;
}

$allFiles = \Component\Eportfolio\FileListMapper::getInstance()->findByColumn('file_id', $image->getId());

$isViewable = false;

if ($allFiles->count()) {
    foreach ($allFiles as $currentFile) {
        $authenticator = new Eportfolio_Authenticator($_SESSION['user'], $image->getOwner(), $currentFile->getPageId());

        /* If you can view one of the pages that this file is shared on,
         * break out of here
         */
        if ($authenticator->canView()) {
            $isViewable = true;

            break;
        }
    }
}

/* If the file was never viewable on any eportfolio page and you aren't
 * the owner, get out
 */
if (!$isViewable && ($_SESSION['user']->getId() != $image->getOwner()->getId()) && !$_SESSION['user']->isSuperuser()) {
    error(5);
}

if (isset($_GET['key'])) {
    $transforms = new Image\Transformation\Storage();
    $cacheKey   = $transforms->getCacheKey($_GET['key']);
    $transform  = $transforms->retrieve($_GET['key']);
} elseif (isset($_GET['width']) && isset($_GET['height'])) {
    $transforms = new Image\Transformation\MyImage();
    $cacheKey   = $transforms->getCacheKey((int)$_GET['width'], (int)$_GET['height']);
    $transform  = $transforms->retrieve((int)$_GET['width'], (int)$_GET['height']);
} else {
    redirectToBroken(400);
    exit;
}

/** @var Storage\Model $storageModel */
$storageModel = $image->getStorage();

/** @var ProcessorFactory $processorFactory */
$processorFactory = $kernel->get('filesystem.imageProcessorFactory');
$processor        = $processorFactory->buildForStorage(
    $storageModel,
    $cacheKey,
    $transform,
);
try {
    $processor->load()->stream();
} catch (Image\Exception\ConcurrentTransformationException $e) {
    $kernel->outputResourceBeingProcessedHeader();
    redirectToBroken($e->getHttpStatusCode());
} catch (SchoolboxNotFoundException $e) {
    redirectToBroken(404);
} catch (Exception $e) {
    redirectToBroken(500);
}

exit;

/**
 * @param int $code
 */
function redirectToBroken($code = 500)
{
    if (isset($_GET['key'])) {
        SitePage::redirectToBrokenImageWithTransform('storage', $_GET['key'], $code);
    } elseif (isset($_GET['width'])) {
        SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height '] ?: $_GET['width'], $code);
    }
}
