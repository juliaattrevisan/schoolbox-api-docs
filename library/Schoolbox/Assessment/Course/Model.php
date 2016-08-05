<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;


use Schoolbox\Collection;

/**
 * Class Model
 *
 * @deprecated This model will be replaced with one for an actual course object.
 *
 * @package Schoolbox\Assessment\Course
 */
class Model extends \Schoolbox\Resource\Folder\Model
{

    /** @var Collection|Unit\DeprecatedModel[] */
    protected $units;

    /**
     * @return Unit\DeprecatedModel[]|Collection
     */
    public function getUnits()
    {
        return $this->units;
    }


    /**
     * @param Unit\DeprecatedModel[]|Collection $units
     * @return static
     */
    public function setUnits($units)
    {
        $this->units = $units;
        return $this;
    }

}
