<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use enshrined\svgSanitize\Sanitizer;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;

/**
 * Class Svg
 */
class Svg implements ISanitizer
{
    /**
     * Which mime types does this sanitizer accept?
     */
    private const ACCEPTED_MIMETYPE = 'image/svg+xml';

    /**
     * The internal sanitizer this class uses to actually sanitize a SVG.
     */
    private Sanitizer $sanitizer;

    /**
     * Constructor
     *
     * @param Sanitizer $sanitizer
     */
    public function __construct(Sanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * @inheritdoc
     */
    public function accepts(string $mimetype): bool
    {
        return $mimetype === self::ACCEPTED_MIMETYPE;
    }

    /**
     * @inheritdoc
     */
    public function sanitizeBeforeStorage(FilesystemInterface $fileSystem, string $path, bool $dryRun = false): bool
    {
//        $sanitizer = new Sanitizer();
//        $sanitizer->minify(true);
        try {
            $rawSVG = $fileSystem->read($path);
        } catch (FileNotFoundException $e) {
            return false;
        }
        $purifiedSVG = $this->sanitizer->sanitize($rawSVG);
        if (!$purifiedSVG || $rawSVG === $purifiedSVG) {
            return false;
        }
        if ($dryRun) {
            return true;
        }
        return $fileSystem->put($path, $purifiedSVG);
    }
}
