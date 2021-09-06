<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use Schoolbox\IConfigLoader;

/**
 * Sanitizer for HTML files.
 */
class Exif implements ISanitizer
{
    /**
     * Which mime types does this sanitizer accept?
     */
    private const ACCEPTED_MIMETYPE = ['image/jpeg', 'image/tiff'];

    /**
     * @var IConfigLoader
     */
    private IConfigLoader $config;

    /**
     * Constructor
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function accepts(string $mimetype): bool
    {
        return $this->config->get('remove_image_exif')
            && in_array($mimetype, self::ACCEPTED_MIMETYPE);
    }

    /**
     * @inheritdoc
     */
    public function sanitize(string $mimetype, string $contents, ?string $path = null): ?string
    {
        if (!$this->accepts($mimetype) || null === $path) {
            return null;
        }

        $image = new \Imagick($path);
        $properties = $image->getImageProperties();
//        foreach ($properties as $name => $value) {
//            if (preg_match('/exif:GPS(.*)/i', $name, $matches)) {
//                    $image->deleteImageProperty($name);
//            }
//        }
        $image->stripImage();
        $image->writeImage();
        /** @var string $image */
        return $image->__tostring();
    }
}
