<?php

/**
 * Twig function for outputting an image as a data: URL.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Twig\Error\RuntimeError;

class InlineImage
{

    /**
     * Outputs the image in $source (based in WEB_root) as a data: URL. Adds the
     * image mime type as well.
     *
     * @param  string $source
     * @return string
     * @throws RuntimeError if the file can't be read or doesn't exist.
     */
    public static function execute($source)
    {
        $realpath = WEB_root . '/' . $source;
        if (!(is_file($realpath) && is_readable($realpath))) {
            throw new RuntimeError('Image file "' . $realpath . '" does not exist');
        }
        return sprintf(
            'data:%s;base64,%s',
            image_type_to_mime_type(exif_imagetype($realpath)),
            base64_encode(file_get_contents($realpath))
        );
    }
}
