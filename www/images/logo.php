<?php

/**
 * Outputs an icon from a spritesheet at a given size, or with a given transform
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Image;
use Alaress\Image\Transformation;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->outputCacheControlMaxAgeHeaderFromConfig(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT);

$logoSizes = [
    'skin_logo_square' => [
        'normal'   => [80, 80, '/core/m-hercules.png'],
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

$logo = $_GET['logo'];
$size = $_GET['size'];

if (!(array_key_exists($logo, $logoSizes) && array_key_exists($size, $logoSizes[$logo]))) {
    SitePage::redirectToBrokenImageOfSize(80, 80, 404);
}


try {
    /* Choose a caching method to use
     */
    $key            = $logoSizes[$logo][$size][0] . 'x' . $logoSizes[$logo][$size][1];
    $hash           = Config::get($logo);
    if (!$hash) {
        // Quick hack to prevent avoidable db query (shouldn't be throwing exceptions for this)
        throw new Exception("No hash configured for logo '$logo'");
    }
    $resource       = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($hash);
    $fileSystem     = $resource->getFilesystem();

    if ($fileSystem instanceof Filesystem && $fileSystem->getAdapter() instanceof AwsS3Adapter) {
        /** @var IConfigLoader $config */
        $config = $kernel->get('config');

        if ($config->get(ConfigMapper::NAME_IMAGE_S3_PRESIGNED_URL_ENABLED)) {
            $preSignedUrlService = new PreSignedUrlService($config);

            $resourcePath = $resource->getPath();

            $preSignedUrlDataObject = $preSignedUrlService->generate(
                $resource,
                Alaress_Date_Format::now()->addSeconds($config->get(ConfigMapper::NAME_BROWSER_CACHE_IMAGE_TIMEOUT)),
                ($key && strpos($resourcePath, '-') === false) ? $resourcePath . '-' . $key : $resourcePath
            );

            $preSignedUrlService->download($preSignedUrlDataObject);
        }
    }

    /* Process and stream image
     */
    $transform      = (new Transformation\MyImage())->retrieve($logoSizes[$logo][$size][0], $logoSizes[$logo][$size][1]);
    $imageProcessor = new Image\Processor($resource, $key, $transform);
    $imageProcessor->load()->stream();
} catch (Exception $e) {
    // redirect to the placeholder
    /** @var PrefixGenerator $cdnPrefixGenerator */
    $cdnPrefixGenerator = $kernel->get('cdnPrefixGenerator');
    SitePage::localRedir($cdnPrefixGenerator->generate() . '/images' . $logoSizes[$logo][$size][2]);
}
