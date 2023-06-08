<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Serializable;

/**
 * Trait for implementing the legacy `Serializable` interface.
 */
trait LegacySerializable
{
    /**
     * Classes which use this trait must implement this method from
     * `Serializable`.
     *
     * @return array
     */
    abstract public function __serialize(): array;

    /**
     * Classes which use this trait must implement this method from
     * `Serializable`.
     *
     * @param array $serialized
     * @return void
     */
    abstract public function __unserialize(array $serialized): void;

    /**
     * Serializes the object.
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize($this->__serialize());
    }

    /**
     * Unserializes the object.
     *
     * @param string $serialized the string representation of the object.
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->__unserialize(unserialize($serialized));
    }
}
