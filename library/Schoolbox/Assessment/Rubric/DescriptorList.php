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
use Schoolbox\GenericCollection;

/**
 * Class DescriptorList
 *
 * @deprecated use `\Schoolbox\Assessment\Rubric\Grid\Grid` as a descriptor container instead
 */
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
        trigger_error(
            'This class is deprecated; use "\Schoolbox\Assessment\Rubric\Grid\Grid" as a descriptor container instead',
            E_USER_DEPRECATED
        );
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
    public function putDescriptor(Descriptor $descriptor, $crossReferenceId)
    {
        if (!$this->descriptors->contains($descriptor)) {
            $this->descriptors->attach($descriptor, $crossReferenceId);
        }

        return $this;
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
            'descriptors' => [],
        ];

        foreach ($this->descriptors as $descriptor) {
            $crossReferenceId                          = $this->descriptors->offsetGet($descriptor);
            $context['descriptors'][$crossReferenceId] = $descriptor->toContext();
        }

        return $context;
    }


    /**
     * @inheritdoc
     *
     * @return float|null
     */
    public function calculateMark()
    {
        $sum = null;

        foreach ($this->descriptors as $descriptor) {
            $descriptorMark = $descriptor->calculateMark();

            // Only adds to the sum if descriptor is selected, due to logic in Descriptor::calculateMark()
            if ($descriptorMark !== null) {
                $sum += $descriptor->calculateMark();
            }
        }

        return $sum;
    }


    /**
     * Returns a collection containing all the descriptors in this container, for conversion to ORM and subsequent DB persistence.
     *
     * @return Collection|Descriptor[]
     */
    public function getAllDescriptors()
    {
        $c = new GenericCollection(Descriptor::class);

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
        return $this->getAllDescriptors()->filter(function (Descriptor $item){
            return $item->isSelected();
        });
    }

}
