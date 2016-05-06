<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;


use Schoolbox\Collection;

class Model extends \Schoolbox\Resource\Folder\Model
{

    /** @var Collection|Unit\Model[] */
    protected $units;

    /**
     * @return Unit\Model[]|Collection
     */
    public function getUnits()
    {
        return $this->units;
    }


    /**
     * @param Unit\Model[]|Collection $units
     * @return static
     */
    public function setUnits($units)
    {
        $this->units = $units;
        return $this;
    }

}
