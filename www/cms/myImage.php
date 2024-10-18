<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Transformation\MyImage;
use Schoolbox\Application\Exception\NotFound as SchoolboxNotFoundException;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Storage;

require_once __DIR__ . '/../../conf/core/require.php';

[$width, $height] = MyImage::ensureDimensionsAreReasonable($_GET['width'] ?? null, $_GET['height'] ?? null);

$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->startSentryTransaction(__FILE__);
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () use ($width, $height) {
        header('Cache-Control: no-store');
        redirectToBroken($width, $height, 504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

if (empty($_SESSION['id'])) {
    redirectToBroken($width, $height, 403);
}

try {
    $image = Schoolbox\Resource\My\File\Mapper::getInstance()->findById($_GET['id']);
} catch (Schoolbox\Mapper\Exception $e) {
    redirectToBroken($width, $height, 404);
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
} else {
    $transforms = new MyImage();
    $cacheKey   = $transforms->getCacheKey($width, $height);
    $transform  = $transforms->retrieve($width, $height);
}

/** @var Storage\Model $storageModel */
$storageModel = $image->getStorage();

/** @var Image\ProcessorFactory $processorFactory */
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
    redirectToBroken($width, $height, $e->getHttpStatusCode());
} catch (SchoolboxNotFoundException $e) {
    redirectToBroken($width, $height, 404);
} catch (Exception $e) {
    redirectToBroken($width, $height, 500);
}

exit;

/**
 * @param int $width
 * @param int $height
 * @param int $code
 */
function redirectToBroken(int $width, int $height, int $code = 500): void
{
    if (isset($_GET['key'])) {
        SitePage::redirectToBrokenImageWithTransform('storage', $_GET['key'], $code);
    } else {
        SitePage::redirectToBrokenImageOfSize($width, $height, $code);
    }
}
