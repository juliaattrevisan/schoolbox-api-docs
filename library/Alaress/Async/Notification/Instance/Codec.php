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
 */
class Codec implements ICodec
{
    /**
     * Constructor
     *
     * @param int  $compressionLevel      Compression level to use when encoding
     * @param bool $permissiveUnserialize Whether to allow all classes when unserializing: otherwise, allows only a
     *                                    known safe set.
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
