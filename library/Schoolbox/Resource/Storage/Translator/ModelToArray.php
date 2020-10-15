<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Translator;

use League\Flysystem\FileNotFoundException;
use Schoolbox\Resource\Storage\Model;

/**
 * Translator for translating a storage object model into an array, ready for
 * JSON encoding.
 */
class ModelToArray
{
    /**
     * @param Model $storage
     * @return array
     */
    public function __invoke(Model $storage): array
    {
        // This may have different links depending on what kind of file it is
        $links = [
            'fetch' => "/storage/fetch.php?hash={$storage->getHash()}",
        ];
        if ($storage->getMimetype()->isImage()) {
            $links['image'] = "/storage/image.php?hash={$storage->getHash()}";
        }
        if ($storage->getMimetype()->isStreamable()) {
            $links['stream'] = "/cms/sendVideo.php?id={$storage->getHash()}&type=storage";
        }
        if ($storage->getMimetype()->isVideo()) {
            $links['thumbnail'] = "/cms/videoThumb.php?size=poster&id={$storage->getHash()}&type=storage";
        }

        try {
            $size = $storage->getSize();
        } catch (FileNotFoundException $e) {
            $size = null;
        }
        return [
            'hash'   => $storage->getHash(),
            'mime'   => $storage->getMime(),
            'type'   => $storage->getType(),
            'size'   => $size,
            '_links' => $links,
        ];
    }
}
