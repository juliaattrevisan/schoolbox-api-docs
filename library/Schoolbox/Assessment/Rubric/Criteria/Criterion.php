<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric\Criteria;


use Schoolbox\Assessment\Rubric\DescriptorList;

class Criterion extends DescriptorList
{
    /** @var bool */
    protected $readOnly = false;

    /** @var Group */
    protected $group;

    /**
     * Returns the total of the values of descriptors contained herein, irrespective of selection
     *
     * @return float
     */
    public function getTotal()
    {
        $total = 0;

        foreach ($this->descriptors as $descriptor) {
            if(is_numeric($descriptor->getTotal())) {
                $total = max($total, $descriptor->getTotal());
            }
        }

        return $total;
    }


    /**
     * @return boolean
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }


    /**
     * @deprecated method for Mapper-use
     * @return bool
     */
    public function getReadOnly()
    {
        return $this->isReadOnly();
    }


    /**
     * @param boolean $readOnly
     * @return static
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = $readOnly;
        return $this;
    }


    /**
     * @param Group $group
     * @return self
     */
    public function setGroup(Group $group)
    {
        $this->group = $group;
        return $this;
    }


    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->group;
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext()
    {
        $context = parent::toContext();

        $context['used'] = $this->isReadOnly();

        return $context;
    }


}
