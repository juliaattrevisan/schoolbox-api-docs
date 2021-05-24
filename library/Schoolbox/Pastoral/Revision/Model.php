<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use Schoolbox\Pastoral\Model as Pastoral;

class Model extends Pastoral
{
    /** @var int $rootId */
    private $rootId;

    /**
     * @param int $rootId
     *
     * @return self
     */
    public function setRootId($rootId)
    {
        $this->rootId = $rootId;

        return $this;
    }

    /**
     * @return int
     */
    public function getRootId()
    {
        return $this->rootId;
    }
}
