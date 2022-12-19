<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Revision;

use Schoolbox\Pastoral\Model as Pastoral;

class Model extends Pastoral
{
    /** @var int $rootId */
    private int $rootId;

    /**
     * @param int $rootId
     *
     * @return self
     */
    public function setRootId(int $rootId): self
    {
        $this->rootId = $rootId;

        return $this;
    }

    /**
     * @return int
     */
    public function getRootId(): int
    {
        return $this->rootId;
    }
}
