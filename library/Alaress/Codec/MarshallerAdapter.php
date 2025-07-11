<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Codec;

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Throwable;

/**
 * Adapts an `ICodec` instance to Symfony cache's `MarshallerInterface`.
 */
class MarshallerAdapter implements MarshallerInterface
{
    /**
     * Constructor
     *
     * @param ICodec $codec The codec to adapt.
     */
    public function __construct(private ICodec $codec)
    {
    }

    /**
     * @inheritdoc
     */
    public function marshall(array $values, ?array &$failed): array
    {
        $marshalled = [];
        $failed     = [];
        foreach ($values as $key => $value) {
            try {
                $marshalled[$key] = $this->codec->encode($value);
            } catch (Throwable $e) {
                trigger_error(
                    'Could not encode value (message: ' . $e->getMessage() . ')',
                    E_USER_WARNING,
                );
                $failed[] = $key;
            }
        }
        return $marshalled;
    }

    /**
     * @inheritdoc
     */
    public function unmarshall(string $value): mixed
    {
        return $this->codec->decode($value);
    }
}
