<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Mapper\Traits\Model;

use Schoolbox\Sequential;

/**
 * Reusable trait for models that have a numeric sequence.
 *
 * Default sequence base is 1, but using classes can override this if desired.
 *
 * @see \Schoolbox\Sequential
 */
trait HasSequence
{
    /**
     * @var int
     */
    private $sequence;

    /**
     * Returns the first ordinal in the sequence
     *
     * @return int
     */
    public static function sequenceBase(): int
    {
        // Default case is 1-based sequencing. Using class can override this if desired.
        return 1;
    }

    /**
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    /**
     * @param int $sequence
     * @return $this|Sequential
     */
    public function setSequence(int $sequence): Sequential
    {
        $this->sequence = $sequence;
        return $this;
    }
}
