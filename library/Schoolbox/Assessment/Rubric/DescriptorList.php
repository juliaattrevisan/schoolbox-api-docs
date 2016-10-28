<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use Schoolbox\Collection;

abstract class DescriptorList implements Twiggable, Calculable
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var Descriptor[]|CalculableAggregator */
    protected $descriptors;


    /**
     * Insert convenience here
     *
     * @param int    $id
     * @param string $name
     */
    function __construct($id = null, $name = '')
    {
        $this->setName($name);
        $this->setId($id);
        $this->descriptors = new CalculableAggregator();
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @param Descriptor $descriptor
     * @param int        $crossReferenceId
     * @return static
     */
    public function putDescriptor($descriptor, $crossReferenceId)
    {
        if (!$this->descriptors->contains($descriptor)) {
            $this->descriptors->attach($descriptor, $crossReferenceId);
        }

        return $this;
    }


    /**
     * @return float
     */
    public function getPercentage()
    {
        if ($total = $this->getTotal()) {
            return $this->getSum() / $total;
        }

        return 0;
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext()
    {
        $context = [
            'id'          => $this->getId(),
            'name'        => $this->getName(),
            'sum'         => $this->getSum(),
            'total'       => $this->getTotal(),
            'percentage'  => $this->getPercentage(),
            'descriptors' => [],
        ];

        foreach ($this->descriptors as $descriptor) {
            $crossReferenceId                          = $this->descriptors->offsetGet($descriptor);
            $context['descriptors'][$crossReferenceId] = $descriptor->toContext();
        }

        return $context;
    }


    /**
     * Return the calculated sum value of this Calculable (NOT the total possible value)
     *
     * @return float|null
     */
    public function getSum()
    {
        $sum = null;

        foreach ($this->descriptors as $descriptor) {
            $ds = $descriptor->getSum();
            if (!is_null($ds)) {
                $sum += $descriptor->getSum();
            }
        }

        return $sum;
    }


    /**
     * Returns a collection containing all the descriptors in this container, for DB persistence.
     *
     * @return Collection|Descriptor[]
     */
    public function getAllDescriptors()
    {
        $c = new Collection();

        foreach ($this->descriptors as $descriptor) {
            $c->add($descriptor);
        }

        return $c;
    }


    /**
     * @return Collection|Descriptor[]
     */
    public function getSelectedDescriptors()
    {
        return $this->getAllDescriptors()->filter(function ($item){
            return $item->isSelected();
        });
    }

}
