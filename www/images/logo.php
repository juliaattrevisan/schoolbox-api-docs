<?php

/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image\Exception\ConcurrentTransformationException;
use Alaress\Image\ProcessorFactory;
use Alaress\Image\Transformation\MyImage;
use Schoolbox\Application\ResourceLimitExceededHandler;
use Schoolbox\Application\WebRequest\ShutdownHandler;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';

[$width, $height] = MyImage::ensureDimensionsAreReasonable($_GET['width'] ?? null, $_GET['height'] ?? null);

$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);
$kernel->getShutdownHandler()->registerFunction(
    new ResourceLimitExceededHandler(function () use ($width, $height) {
        header('Cache-Control: no-store');
        SitePage::redirectToBrokenImageOfSize($width, $height, 504);
    }),
    ShutdownHandler::PRIORITY_HIGHEST,
);

$logoSizes = [
    ConfigMapper::NAME_SKIN_FAVICON => [
        'normal'   => [72, 72, '/core/m-hercules.png'],
        'hidpi'    => [160, 160, '/core/m-hercules.png'],
        'favicon'  => [64, 64, '/core/m-hercules.png'],
        'tile70'   => [70, 70, '/core/m-hercules.png'],
        'tile150'  => [150, 150, '/core/m-hercules.png'],
        'apple57'  => [57, 57, '/core/m-hercules.png'],
        'apple60'  => [60, 60, '/core/m-hercules.png'],
        'apple72'  => [72, 72, '/core/m-hercules.png'],
        'apple76'  => [76, 76, '/core/m-hercules.png'],
        'apple114' => [114, 114, '/core/m-hercules.png'],
        'apple120' => [120, 120, '/core/m-hercules.png'],
        'apple144' => [144, 144, '/core/m-hercules.png'],
        'apple152' => [152, 152, '/core/m-hercules.png'],
        'apple180' => [180, 180, '/core/m-hercules.png'],
        'google96'  => [96,96, '/core/m-hercules.png'],
        'google128' => [128,128, '/core/m-hercules.png'],
        'google192' => [192,192, '/core/m-hercules.png'],
        'google384' => [384,384, '/core/m-hercules.png'],
        'google512' => [512,512, '/core/m-hercules.png'],
    ],
    'skin_logo_square' => [
        'normal'   => [72, 72, '/core/m-hercules.png'],
        'hidpi'    => [160, 160, '/core/m-hercules.png'],
        'favicon'  => [64, 64, '/core/m-hercules.png'],
        'tile70'   => [70, 70, '/core/m-hercules.png'],
        'tile150'  => [150, 150, '/core/m-hercules.png'],
        'apple57'  => [57, 57, '/core/m-hercules.png'],
        'apple60'  => [60, 60, '/core/m-hercules.png'],
        'apple72'  => [72, 72, '/core/m-hercules.png'],
        'apple76'  => [76, 76, '/core/m-hercules.png'],
        'apple114' => [114, 114, '/core/m-hercules.png'],
        'apple120' => [120, 120, '/core/m-hercules.png'],
        'apple144' => [144, 144, '/core/m-hercules.png'],
        'apple152' => [152, 152, '/core/m-hercules.png'],
        'apple180' => [180, 180, '/core/m-hercules.png'],
        'google96'  => [96,96, '/core/m-hercules.png'],
        'google128' => [128,128, '/core/m-hercules.png'],
        'google192' => [192,192, '/core/m-hercules.png'],
        'google384' => [384,384, '/core/m-hercules.png'],
        'google512' => [512,512, '/core/m-hercules.png'],
    ],
    'skin_logo_large'  => [
        'favicon' => [64, 64, '/core/hercules-64x64.png'],
        'normal'  => [196, 400, '/core/hercules.png'],
        'hidpi'   => [392, 800, '/core/hercules.png'],
        'tile310' => [310, 310, '/core/hercules.png'],
    ],
    'skin_logo_login'  => [
        'normal'  => [296, 400, '/core/logo.png'],
        'hidpi'   => [592, 800, '/core/logo.png'],
        'tile310' => [310, 150, '/core/logo.png'],
    ],
];

if (!array_key_exists('logo', $_GET) || !array_key_exists('size', $_GET)) {
    SitePage::redirectToBrokenImageOfSize($width, $height, 404);
}

$logo = $_GET['logo'];
$size = $_GET['size'];

if (!array_key_exists($logo, $logoSizes) || !array_key_exists($size, $logoSizes[$logo])) {
    SitePage::redirectToBrokenImageOfSize($width, $height, 404);
}

$myImage = new MyImage();
$key     = $myImage->getCacheKey($logoSizes[$logo][$size][0], $logoSizes[$logo][$size][1]);
/** @var IConfigLoader $config */
$config = $kernel->get('config');
try {
    /* Choose a caching method to use
     */
    $hash            = $config->get($logo);
    if ('' === trim((string)$hash)) {
        // If user has not set a favicon, then use the default NAME_SKIN_LOGO_SQUARE or just fail
        $hash = $config->get(ConfigMapper::NAME_SKIN_LOGO_SQUARE);

        if ('' === trim((string)$hash)) {
            // Quick hack to prevent avoidable db query (shouldn't be throwing exceptions for this)
            throw new Exception("No hash configured for logo '$logo'");
        }
    }
    $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($hash);
    if ($config->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3) {
        if ($config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)) {
            $preSignedUrlService = new PreSignedUrlService($config);

            $resourcePath = FilesystemProxy::getPathFromHash($resource->getHash());

            $preSignedUrlDataObject = $preSignedUrlService->generate(
                $resource,
                Alaress_Date_Format::now()->setTimezone('UTC')->addSeconds((int)$config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                ($key && strpos($resourcePath, '-') === false) ? $resourcePath . '-' . $key : $resourcePath
            );

            $preSignedUrlService->download($preSignedUrlDataObject);
        }
    }

    /* Process and stream image
     */
    /** @var ProcessorFactory $processorFactory */
    $processorFactory = $kernel->get('filesystem.imageProcessorFactory');
    if ($logo == ConfigMapper::NAME_SKIN_FAVICON) {
        $transform        = $myImage->square($logoSizes[$logo][$size][0]);
    } else {
        $transform        = $myImage->retrieve($logoSizes[$logo][$size][0], $logoSizes[$logo][$size][1]);
    }
    $imageProcessor   = $processorFactory->buildForStorage(
        $resource,
        $key,
        $transform,
    );
    $imageProcessor->load()->stream();
    exit;
} catch (ConcurrentTransformationException | InvalidPresignedUrlDurationExecption $e) {
    $kernel->outputResourceBeingProcessedHeader();
    $responseCode = $e->getHttpStatusCode();
    $placeHolderFile = WEB_root . "/static/images" . $logoSizes[$logo][$size][2];
    if (is_readable($placeHolderFile)) {
        header('Content-type: image/png');
        readfile($placeHolderFile);
        exit;
    }
    SitePage::redirectToBrokenImageOfSize($width, $height, $responseCode);
} catch (Exception $e) {
    // redirect to the placeholder
    /** @var PrefixGenerator $cdnPrefixGenerator */
    $cdnPrefixGenerator = $kernel->get('cdnPrefixGenerator');

    // Temporary Redirect: eventually someone will upload a logo
    header('cache-control: no-store');
    $cdnPrefixGenerator->redirectTemporary('/images' . $logoSizes[$logo][$size][2]);
}
