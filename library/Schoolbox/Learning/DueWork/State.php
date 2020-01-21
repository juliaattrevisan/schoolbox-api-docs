<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\DueWork;

use \Schoolbox\Sequential;
use \Schoolbox\Mapper\Traits\Model as ModelTrait;

class State implements Sequential
{
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\HasSequence;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $code;

    /**
     * @var string
     */
    protected $displayAs;

    /**
     * @var float
     */
    protected $normaliseAs;

    /**
     * @param  string $code
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param  string $displayAs
     *
     * @return $this
     */
    public function setDisplayAs($displayAs)
    {
        $this->displayAs = $displayAs;
        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayAs()
    {
        return $this->displayAs;
    }

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  float $normaliseAs
     *
     * @return $this
     */
    public function setNormaliseAs($normaliseAs)
    {
        $this->normaliseAs = $normaliseAs;
        return $this;
    }

    /**
     * @return float
     */
    public function getNormaliseAs()
    {
        return $this->normaliseAs;
    }
}
