<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Instance;

use Alaress\Async\Notification\Parameter\Param;
use Alaress\Codec\ICodec;
use Schoolbox\Realtime\Notification\Parameter\SimpleParam;

/**
 * Codec for `Instance` objects.
 *
 * The extracted code was also used to compress `TriggerInterface` objects:
 * I don't know why, or how they are supposed to be uncompressed as the
 * unserialize call didn't allow them to be reconstructed.
 */
class Codec implements ICodec
{
    /**
     * Constructor
     *
     * @param int  $compressionLevel      Compression level to use when encoding
     * @param bool $permissiveUnserialize Whether to allow all classes when unserializing: otherwise, allows only a
     *                                    known safe set:
     *                                    * `\Alaress\Async\Notification\Instance\Instance`
     *                                    * `\Alaress\Async\Notification\Parameter\Param`
     *                                    * `\Schoolbox\Realtime\Notification\Parameter\SimpleParam`
     */
    public function __construct(
        private int $compressionLevel,
        private bool $permissiveUnserialize = false,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function encode($rawValue)
    {
        return gzcompress(serialize($rawValue), $this->compressionLevel);
    }

    /**
     * @inheritdoc
     */
    public function decode($encodedValue)
    {
        $classes = $this->permissiveUnserialize
            ? true
            : [Instance::class, Param::class, SimpleParam::class];
        return unserialize(gzuncompress($encodedValue), ['allowed_classes' => $classes]);
    }
}
