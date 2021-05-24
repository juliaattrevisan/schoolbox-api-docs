<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox;

/**
 * Instances of classes that implement this interface have a logical sequence
 *
 * In most cases, a class can implement this interface by using a trait
 * @see \Schoolbox\Mapper\Traits\Model\HasSequence
 */
interface Sequential
{
    /**
     * Returns the first ordinal in the sequence
     *
     * @return int
     */
    public static function sequenceBase(): int;

    /**
     * @return int|null
     */
    public function getSequence(): ?int;

    /**
     * @param int $sequence
     * @return $this
     */
    public function setSequence(int $sequence): self;
}
