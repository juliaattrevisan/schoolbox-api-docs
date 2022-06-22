<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Translator;

use League\Flysystem\UnableToReadFile;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Resource\Storage\Model;

/**
 * Translator for translating a storage object model into an array, ready for
 * JSON encoding.
 */
class ModelToArray
{
    /**
     * Prefix generator: for attaching a prefix to the mime icon
     */
    private PrefixGenerator $prefixGenerator;

    /**
     * Constructor
     *
     * @param PrefixGenerator $prefixGenerator
     */
    public function __construct(PrefixGenerator $prefixGenerator)
    {
        $this->prefixGenerator = $prefixGenerator;
    }

    /**
     * @param Model $storage
     * @return array
     */
    public function __invoke(Model $storage): array
    {
        // This may have different links depending on what kind of file it is
        $type  = null;
        $links = [];

        if ($storage->getHash()) {
            $links['fetch'] = "/storage/fetch.php?hash={$storage->getHash()}";
        }

        if ($storage->getMimetype()) {
            $type = $storage->getMimetype()->getType();
            $links['icon'] = sprintf(
                '%s/images/mime/%s',
                $this->prefixGenerator->generate(),
                $storage->getMimetype()->getIconFile()
            );
            if ($storage->getMimetype()->isImage()) {
                $links['image'] = "/storage/image.php?hash={$storage->getHash()}";
            }
            if ($storage->getMimetype()->isStreamable()) {
                $links['stream'] = "/cms/sendVideo.php?id={$storage->getHash()}&type=storage";
            }
            if ($storage->getMimetype()->isVideo()) {
                $links['poster'] = "/cms/videoThumb.php?size=poster&id={$storage->getHash()}&type=storage";
            }
        }

        try {
            $size = $storage->getSize();
        } catch (UnableToReadFile $e) {
            // There might not be a file attached
            $size = null;
        }
        return [
            'hash'   => $storage->getHash(),
            'mime'   => $storage->getMime(),
            'type'   => $type,
            'size'   => $size,
            '_links' => $links,
        ];
    }
}
