<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use Schoolbox\Pastoral\Model as Pastoral;

class Model extends Pastoral
{
    /** @var int $rootId */
    protected $rootId;

    /**
     * @param int $rootId
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